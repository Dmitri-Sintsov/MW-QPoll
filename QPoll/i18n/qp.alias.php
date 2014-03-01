<?php
/**
 * Aliases for special pages
 *
 * @file
 * @ingroup Extensions
 */

$specialPageAliases = array();

/** English (English) */
$specialPageAliases['en'] = array(
	'PollResults' => array( 'PollResults' ),
);

/** Arabic (العربية) */
$specialPageAliases['ar'] = array(
	'PollResults' => array( 'نتائج_الاستقصاء' ),
);

/** Luxembourgish (Lëtzebuergesch) */
$specialPageAliases['lb'] = array(
	'PollResults' => array( 'Resultater_vun_der_Ëmfro' ),
);

/** Macedonian (Македонски) */
$specialPageAliases['mk'] = array(
	'PollResults' => array( 'АнкетниРезултати' ),
);

/** Nedersaksisch (Nedersaksisch) */
$specialPageAliases['nds-nl'] = array(
	'PollResults' => array( 'Peilingsresultaoten' ),
);

/** Dutch (Nederlands) */
$specialPageAliases['nl'] = array(
	'PollResults' => array( 'Peilingresultaten' ),
);

/** Russian (Русский) */
$specialPageAliases['ru'] = array(
	'PollResults' => array( 'Результаты_опросов' ),
);

/**
 * For backwards compatibility with MediaWiki 1.15 and earlier.
 */
$aliases =& $specialPageAliases;