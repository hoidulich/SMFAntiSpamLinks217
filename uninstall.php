<?php
/**
 * Uninstaller for Anti-Spam Links for SMF 2.1.x.
 */

global $boarddir, $sourcedir, $cacheAPI;

$manual = false;

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
{
	require_once dirname(__FILE__) . '/SSI.php';
	$manual = true;
}
elseif (!defined('SMF'))
	die('This uninstaller must be run from within SMF.');

if ($manual && !function_exists('remove_integration_function'))
	require_once $sourcedir . '/Subs.php';

if ($manual)
{
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
		remove_integration_function($hook[0], $hook[1], true, '$sourcedir/AntiSpamLinks.php');
}

updateSettings(array(
	'anti_spam_links_nolinks' => null,
	'anti_spam_links_newbielinks' => null,
	'anti_spam_links_nofollowlinks' => null,
	'anti_spam_links_guests' => null,
));

$package_files = array(
	$sourcedir . '/AntiSpamLinks.php',
	$boarddir . '/Themes/default/languages/AntiSpamLinks.english.php',
	$boarddir . '/Themes/default/languages/AntiSpamLinks.vietnamese.php',
);

if ($manual)
{
	foreach ($package_files as $file)
		if (file_exists($file))
			@unlink($file);
}

if (isset($cacheAPI) && is_object($cacheAPI) && is_callable(array($cacheAPI, 'cleanCache')))
	$cacheAPI->cleanCache();

if ($manual)
{
	header('Content-Type: text/plain; charset=UTF-8');
	echo "Anti-Spam Links for SMF 2.1.x uninstalled.\n";
}

