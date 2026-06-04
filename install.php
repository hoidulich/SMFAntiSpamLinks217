<?php
/**
 * Installer for Anti-Spam Links for SMF 2.1.x.
 */

global $boarddir, $sourcedir, $modSettings, $cacheAPI;

$manual = false;

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
{
	require_once dirname(__FILE__) . '/SSI.php';
	$manual = true;
}
elseif (!defined('SMF'))
	die('This installer must be run from within SMF.');

if ($manual && !function_exists('add_integration_function'))
	require_once $sourcedir . '/Subs.php';

$package_files = array(
	array(
		'source' => dirname(__FILE__) . '/SMF-2.1/Sources/AntiSpamLinks.php',
		'destination' => $sourcedir . '/AntiSpamLinks.php',
	),
	array(
		'source' => dirname(__FILE__) . '/SMF-2.1/Themes/default/languages/AntiSpamLinks.english.php',
		'destination' => $boarddir . '/Themes/default/languages/AntiSpamLinks.english.php',
	),
	array(
		'source' => dirname(__FILE__) . '/SMF-2.1/Themes/default/languages/AntiSpamLinks.vietnamese.php',
		'destination' => $boarddir . '/Themes/default/languages/AntiSpamLinks.vietnamese.php',
	),
);

if ($manual)
{
	foreach ($package_files as $file)
	{
		if (!file_exists($file['source']))
			continue;

		@copy($file['source'], $file['destination']);
		@chmod($file['destination'], 0644);
	}

	$hooks = array(
		array('integrate_load_theme', 'AntiSpamLinks_LoadTheme'),
		array('integrate_modify_post_settings', 'AntiSpamLinks_ModifyPostSettings'),
		array('integrate_save_post_settings', 'AntiSpamLinks_SavePostSettings'),
		array('integrate_post_errors', 'AntiSpamLinks_PostErrors'),
		array('integrate_preview_post', 'AntiSpamLinks_PreviewPost'),
		array('integrate_post_JavascriptModify', 'AntiSpamLinks_JavaScriptModify'),
		array('integrate_jsmodify_xml', 'AntiSpamLinks_JavaScriptModifyXml'),
		array('integrate_prepare_display_context', 'AntiSpamLinks_PrepareDisplayContext'),
		array('integrate_getTopic_previous_post', 'AntiSpamLinks_PreviousPost'),
		array('integrate_member_context', 'AntiSpamLinks_MemberContext'),
	);

	foreach ($hooks as $hook)
		add_integration_function($hook[0], $hook[1], true, '$sourcedir/AntiSpamLinks.php');
}

$defaults = array(
	'anti_spam_links_nolinks' => 0,
	'anti_spam_links_newbielinks' => 0,
	'anti_spam_links_nofollowlinks' => 0,
	'anti_spam_links_guests' => 0,
);

$to_update = array();
foreach ($defaults as $setting => $value)
{
	if (!isset($modSettings[$setting]))
		$to_update[$setting] = $value;
}

if (!empty($to_update))
	updateSettings($to_update);

if (isset($cacheAPI) && is_object($cacheAPI) && is_callable(array($cacheAPI, 'cleanCache')))
	$cacheAPI->cleanCache();

if ($manual)
{
	header('Content-Type: text/plain; charset=UTF-8');
	echo "Anti-Spam Links for SMF 2.1.x installed.\n";
}

