<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This file is part of the QPoll extension. It is not a valid entry point.\n" );
}

class qp_FreeQuestionOptions extends qp_TextQuestionOptions {

	## start new proposal with specified name.
	#    possible values: null (continue current proposal), not null (start new proposal
	#    with the specified proposal name):
	# <<:: user>> <<:: name="user" width="12">> <[subscription]> <[name="subscription" checked=""]>
	#
	# For descriptions of 'catreq' and 'emptytext' attributes, look at qp_PropAttrs class.
	protected static $proposalAttributes = array( 'name', 'catreq', 'emptytext' );

	function __construct() {
		# for question type="text", proposal attributes are defined inside category:
		foreach ( self::$proposalAttributes as $attr ) {
			$this->attributes[$attr] = null;
			$this->textOptionAttributes[] = $attr;
			$this->switchOptionAttributes[] = $attr;
		}
		# Special attributes 'pid' / 'cid' to be stored in proposal view.
		# These attributes are not part of $this->textOptionAttributes /
		# $this->switchOptionAttributes, thus, will not be extracted from source text.
		# Will be automatically cleared by $this->startOptionsList();
		$this->attributes['prop_id'] = $this->attributes['cat_id'] = null;
	}

} /* end of qp_FreeQuestionOptions class */

class qp_FreeQuestionProposal {

	# proposal id
	var $id;
	# count of categories
	var $catCount = 0;
	# list of dbtokens
	var $dbtokens = array();
	# catReq attribute
	var $catReq = null;

	function __construct( $proposal_id ) {
		$this->id = $proposal_id;
	}

} /* end of qp_FreeQuestionProposal class */

class qp_FreeQuestion extends qp_TextQuestion {

	# current instance of proposal in use: one of $this->props elements;
	# null means there is no proposal defined yet.
	var $currProp = null;

	# buffer to store proposal parts defined before the first category definition
	var $firstPropPart = '';

	# array of qp_FreeQuestionProposal instances
	# key: proposal id
	var $props;

	function addProposal( $name ) {
		$prop_id = count( $this->props );
		if ( $name !== null ) {
			$this->mProposalNames[$prop_id] = $name;
		}
		$this->currProp =
		$this->props[$prop_id] =
			new qp_FreeQuestionProposal( $prop_id );
	}

	function setCategory( $name, $text_answer, $answered ) {
		# finally, add new category input options for the view
		$this->opt->closeCategory();
		$this->opt->attributes['prop_id'] = $this->currProp->id;
		$this->opt->attributes['cat_id'] = $this->currProp->catCount;
		if ( $this->opt->hasOverflow !== false ) {
			$msg_key = ( $this->opt->hasOverflow === 0 ) ? 'qp_error_too_long_category_option_value' : 'qp_error_too_long_category_options_values';
			$this->view->addErrorToken( wfMsg( $msg_key ), 'error' );
		}
		$this->view->addCatDef(
			$this->opt,
			$name,
			$text_answer,
			$this->poll->mBeingCorrected && !$answered
		);
		# current category is over
		$this->currProp->catCount++;
	}

	function addCategory() {
		# new proposal name or null for already existing proposal
		if ( ( $name = $this->opt->attributes['name'] ) !== null ) {
			# option possibly switches current proposal in use
			if ( is_numeric( $name ) ) {
				# invalid proposal name specified
				$this->view->addErrorToken( 'qp_error_numeric_proposal_name', 'error' );
			} else {
				if ( ( $prop_id = $this->getProposalIdByName( $name ) ) === false ) {
					# start new proposal
					$this->addProposal( $name );
				} else {
					# continue existing proposal
					$this->currProp = $this->props[$prop_id];
				}
			}
		}
		if ( $this->currProp === null ) {
			# first category should have proposal name defined
			$this->view->addErrorToken( 'qp_error_undefined_proposal', 'error' );
			return;
		}
		# flush firstPropPart, if not empty
		# please note that $this->currProp is NOT null here
		if ( $this->firstPropPart !== '' ) {
			$this->addProposalPart( $this->firstPropPart );
		}
		# add input options
		$this->currProp->dbtokens[] = $this->opt->input_options;
		if ( ( $catreq = $this->opt->attributes['catreq'] ) !== null ) {
			# current category defined catreq for current proposal
			$this->currProp->catReq = $catreq;
		}
		# setup mCategories
		$this->mCategories[$this->currProp->catCount] = array( 'name' => strval( $this->currProp->catCount ) );
		# default value of emptytext attribute
		$emptytext = $this->mEmptyText;
		if ( ( $et_attr = $this->opt->attributes['emptytext'] ) !== null ) {
			$emptytext = qp_PropAttrs::getSaneEmptyText( $et_attr );
		}
		# load proposal/category answer (when available)
		$this->loadProposalCategory( $prop_id, $this->currProp->catCount, $emptytext );
	}

	function addProposalPart( $token ) {
		if ( $this->currProp === null ) {
			# no proposals were defined yet; store proposal parts into temporary buffer
			$this->firstPropPart .= strval( $token );
			return;
		}
		$dbtokens_idx = count( $this->currProp->dbtokens );
		if ( $dbtokens_idx > 0 && is_string( $this->currProp->dbtokens[$dbtokens_idx - 1] ) ) {
			# last dbtoken is proposal part
			$this->currProp->dbtokens[$dbtokens_idx - 1] .= strval( $token );
		} else {
			# the first dbtoken or last dbtoken is category options
			$this->currProp->dbtokens[$dbtokens_idx] = strval( $token );
		}
		$this->view->addProposalPart( $token );
	}

	/**
	 * Split raw question body into raw proposals and optionally
	 * raw categories / raw category spans, when available.
	 */
	function splitRawProposals( $input ) {
		# multiline proposals
		# pure wikitext
		# no '\' proposal continuation
		$this->raws = $input;
	}

	function parseTokens( $proposalId ) {
		throw new MWException( 'Method not implemented' );
	}

	/**
	 * Builds proposal views and list of dbtokens for the every defined proposal.
	 */
	function parseAllTokens() {
		# common (united) brace stack (common to all proposals);
		$brace_stack = array();
		$matching_closed_brace = '';
		foreach ( $this->rawtokens as $tkey => $token ) {
			# $toBeStored == true when current $token has to be stored into
			# category / proposal list (depending on $this->opt->isCatDef)
			$toBeStored = true;
			if ( $token === '|' ) {
				# parameters separator
				if ( $this->opt->isCatDef ) {
					if ( count( $brace_stack ) == 1 && $brace_stack[0] === $matching_closed_brace ) {
						# pipe char starts new option only at top brace level,
						# with matching input brace
						$this->opt->addEmptyOption();
						$toBeStored = false;
					}
				}
			} elseif ( array_key_exists( $tkey, $this->brace_matches ) ) {
				# brace
				$brace_match = &$this->brace_matches[$tkey];
				if ( array_key_exists( 'closed_at', $brace_match ) &&
						$brace_match['closed_at'] !== false ) {
					# valid opening brace
					array_push( $brace_stack, $this->matching_braces[$token] );
					if ( array_key_exists( 'iscat', $brace_match ) ) {
						# start category definition
						$matching_closed_brace = $this->matching_braces[$token];
						$this->opt->startOptionsList( $this->input_braces_types[$token] );
						$toBeStored = false;
					}
				} elseif ( array_key_exists( 'opened_at', $brace_match ) &&
					$brace_match['opened_at'] !== false ) {
					# valid closing brace
					array_pop( $brace_stack );
					if ( array_key_exists( 'iscat', $brace_match ) ) {
						$matching_closed_brace = '';
						# add new category input options for the storage
						$this->addCategory();
						$toBeStored = false;
					}
				}
			}
			if ( $toBeStored ) {
				if ( $this->opt->isCatDef ) {
					$this->opt->addToLastOption( $token );
				} else {
					# add new proposal part
					$this->addProposalPart( $token );
				}
			}
		}
	}

	/**
	 * Creates question view which should be renreded and
	 * also may be altered during the poll generation
	 */
	function parseBody() {
		# single instance used to store current category options
		# (no nested categories)
		$this->opt = new qp_FreeQuestionOptions();
		$this->opt->reset();
		$this->rawtokens = preg_split(
			$this->propCatPattern,
			$this->raws,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);
		$this->findMatchingBraces();
		$this->backtrackMismatchingBraces();
		$this->parseAllTokens();
		if ( $this->firstPropPart !== '' ) {
			# There was no categories at all.
			# Create stub proposal with the only prop part, no categories.
			$this->addProposal( null );
			# flush $this->firstPropPart
			$this->addProposalPart( '' );
			# proposal without category definitions
			$this->view->prependErrorToken( wfMsg( 'qp_error_too_few_categories' ), 'error' );
		}
		$prop_attrs = qp_Setup::$propAttrs;
		foreach ( $this->props as $prop_id => $prop ) {
			# build $prop_attrs for db storage and hasMissingCategories() validation
			$prop_attrs->name =
				array_key_exists( $this->mProposalNames[$prop_id] ) ?
					$this->mProposalNames[$prop_id] : '';
			$prop_attrs->dbText = serialize( $prop->dbtokens );
			$prop_attrs->catreq = $prop->catReq;
			# build the whole raw DB proposal_text value to check it's maximal length
			if ( strlen( $prop_attrs ) > qp_Setup::$field_max_len['proposal_text'] ) {
				# too long proposal field to store into the DB
				# this is very important check for text questions because
				# category definitions are stored within the proposal text
				$this->view->insertErrorToken( wfMsg( 'qp_error_too_long_proposal_text' ), 'error', $prop_id );
			}
			$this->mProposalText[$prop_id] = strval( $prop_attrs );
			$this->view->setCatReq( $prop_id, $prop->catReq );
			## Check for unanswered categories.
			if ( $this->poll->mBeingCorrected &&
						$prop_attrs->hasMissingCategories(
							$answered_cats_count = $this->getAnsweredCatCount( $prop_id ),
							$prop->catCount
						) ) {
				$prev_state = $this->getState();
				$this->view->insertErrorToken(
					($answered_cats_count > 0) ?
						wfMsg( 'qp_error_not_enough_categories_answered' ) :
						wfMsg( 'qp_error_no_answer' )
					,
					'NA',
					$prop_id
				);
			}
		}
	}

} /* end of qp_FreeQuestion class */
