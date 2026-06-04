<?php
/**
 * Anti-Spam Links for SMF 2.1.x.
 *
 * Hook-based port of the SMF 2.0.x package.
 */

if (!defined('SMF'))
	die('No direct access...');

/**
 * Ensure language strings are available.
 *
 * @return void
 */
function AntiSpamLinks_LoadTheme()
{
	loadLanguage('AntiSpamLinks');
}

/**
 * Add settings to Admin > Posts and Topics > Posts.
 *
 * @param array $config_vars
 * @return void
 */
function AntiSpamLinks_ModifyPostSettings(&$config_vars)
{
	global $txt;

	antiSpamLinks_load_text();

	$config_vars[] = '';
	$config_vars[] = $txt['anti_spam_links'];
	$config_vars[] = array('int', 'anti_spam_links_nolinks', 'subtext' => $txt['anti_spam_links_zero_disable']);
	$config_vars[] = array('int', 'anti_spam_links_newbielinks', 'subtext' => $txt['anti_spam_links_zero_disable']);
	$config_vars[] = array('int', 'anti_spam_links_nofollowlinks', 'subtext' => $txt['anti_spam_links_zero_disable']);
	$config_vars[] = array('select', 'anti_spam_links_guests', array(
		$txt['anti_spam_links_guests_opt0'],
		$txt['anti_spam_links_guests_opt1'],
		$txt['anti_spam_links_guests_opt2'],
		$txt['anti_spam_links_guests_opt3'],
	));
}

/**
 * Normalize settings before SMF saves them.
 *
 * @return void
 */
function AntiSpamLinks_SavePostSettings()
{
	if (!isset($_POST['anti_spam_links_newbielinks'], $_POST['anti_spam_links_nofollowlinks']))
		return;

	$_POST['anti_spam_links_newbielinks'] = max(0, (int) $_POST['anti_spam_links_newbielinks']);
	$_POST['anti_spam_links_nofollowlinks'] = max(0, (int) $_POST['anti_spam_links_nofollowlinks']);
	$_POST['anti_spam_links_nolinks'] = isset($_POST['anti_spam_links_nolinks']) ? max(0, (int) $_POST['anti_spam_links_nolinks']) : 0;
	$_POST['anti_spam_links_guests'] = isset($_POST['anti_spam_links_guests']) ? min(3, max(0, (int) $_POST['anti_spam_links_guests'])) : 0;

	if (!empty($_POST['anti_spam_links_newbielinks']) && !empty($_POST['anti_spam_links_nofollowlinks']) && $_POST['anti_spam_links_nofollowlinks'] <= $_POST['anti_spam_links_newbielinks'])
		$_POST['anti_spam_links_nofollowlinks'] = $_POST['anti_spam_links_newbielinks'] + 1;
}

/**
 * Validate post submission against link rules.
 *
 * @param array $post_errors
 * @param array $minor_errors
 * @param string $form_message
 * @param string $form_subject
 * @return void
 */
function AntiSpamLinks_PostErrors(&$post_errors, &$minor_errors, $form_message, $form_subject)
{
	$poster = antiSpamLinks_get_target_poster_context();

	if (!antiSpamLinks_should_block_links($poster['id_member'], $poster['posts']))
		return;

	if (!antiSpamLinks_message_has_external_link($form_message))
		return;

	antiSpamLinks_load_text();

	$error_key = antiSpamLinks_get_block_error_key($poster['id_member']);

	if (!in_array($error_key, $post_errors, true))
		$post_errors[] = $error_key;
}

/**
 * Adjust preview output for nonactive/nofollow rendering.
 *
 * @param string $form_message
 * @param string $form_subject
 * @return void
 */
function AntiSpamLinks_PreviewPost(&$form_message, &$form_subject)
{
	global $context;

	if (empty($context['preview_message']))
		return;

	$poster = antiSpamLinks_get_target_poster_context();
	$context['preview_message'] = antiSpamLinks_apply_to_html($context['preview_message'], $poster['id_member'], $poster['posts']);
}

/**
 * Validate quick-edit submissions.
 *
 * @param array $post_errors
 * @param array $row
 * @return void
 */
function AntiSpamLinks_JavaScriptModify(&$post_errors, $row)
{
	global $context;

	$poster_id = empty($row['id_member']) ? 0 : (int) $row['id_member'];
	$posts = $poster_id === 0 ? 0 : antiSpamLinks_get_member_posts($poster_id);

	$context['anti_spam_links_jsmodify'] = array(
		'poster_id' => $poster_id,
		'posts' => $posts,
	);

	if (!isset($_POST['message']))
		return;

	if (!antiSpamLinks_should_block_links($poster_id, $posts))
		return;

	if (!antiSpamLinks_message_has_external_link($_POST['message']))
		return;

	antiSpamLinks_load_text();

	$error_key = antiSpamLinks_get_block_error_key($poster_id);
	$context['anti_spam_links_jsmodify_errors'] = array($error_key);

	if (!in_array($error_key, $post_errors, true))
		$post_errors[] = $error_key;
}

/**
 * Mark quick-edit body errors and post-process returned HTML.
 *
 * @return void
 */
function AntiSpamLinks_JavaScriptModifyXml()
{
	global $context;

	if (!empty($context['message']['body']) && !empty($context['anti_spam_links_jsmodify']))
	{
		$context['message']['body'] = antiSpamLinks_apply_to_html(
			$context['message']['body'],
			$context['anti_spam_links_jsmodify']['poster_id'],
			$context['anti_spam_links_jsmodify']['posts']
		);
	}

	if (!empty($context['message']) && !empty($context['anti_spam_links_jsmodify_errors']))
		$context['message']['error_in_body'] = true;
}

/**
 * Post-process displayed message HTML.
 *
 * @param array $output
 * @param array $message
 * @param int $counter
 * @return void
 */
function AntiSpamLinks_PrepareDisplayContext(&$output, &$message, $counter)
{
	$poster_id = !empty($message['id_member']) ? (int) $message['id_member'] : 0;
	$posts = !empty($output['member']['real_posts']) ? (int) $output['member']['real_posts'] : 0;

	$output['body'] = antiSpamLinks_apply_to_html($output['body'], $poster_id, $posts);
}

/**
 * Post-process previous topic summary posts on the editor screen.
 *
 * @param array $row
 * @return void
 */
function AntiSpamLinks_PreviousPost(&$row)
{
	$poster_id = empty($row['id_member']) ? 0 : (int) $row['id_member'];
	$posts = $poster_id === 0 ? 0 : antiSpamLinks_get_member_posts($poster_id);

	$row['body'] = antiSpamLinks_apply_to_html($row['body'], $poster_id, $posts);
}

/**
 * Post-process member signatures after SMF has parsed them.
 *
 * @param array $member
 * @param int $user
 * @param bool $display_custom_fields
 * @return void
 */
function AntiSpamLinks_MemberContext(&$member, $user, $display_custom_fields)
{
	if (empty($member['signature']))
		return;

	$member['signature'] = antiSpamLinks_apply_to_html(
		$member['signature'],
		empty($member['id']) ? 0 : (int) $member['id'],
		isset($member['real_posts']) ? (int) $member['real_posts'] : 0
	);
}

/**
 * Load language strings once.
 *
 * @return void
 */
function antiSpamLinks_load_text()
{
	static $loaded = false;

	if ($loaded)
		return;

	loadLanguage('AntiSpamLinks');
	$loaded = true;
}

/**
 * Resolve the poster context that should be used for validation/preview.
 *
 * @return array
 */
function antiSpamLinks_get_target_poster_context()
{
	global $user_info;

	if (!empty($_REQUEST['msg']))
		return antiSpamLinks_get_message_poster_context((int) $_REQUEST['msg']);

	return array(
		'id_member' => empty($user_info['is_guest']) ? (int) $user_info['id'] : 0,
		'posts' => empty($user_info['is_guest']) ? (int) $user_info['posts'] : 0,
	);
}

/**
 * Load the original poster context for a message.
 *
 * @param int $message_id
 * @return array
 */
function antiSpamLinks_get_message_poster_context($message_id)
{
	global $smcFunc;
	static $cache = array();

	$message_id = (int) $message_id;
	if (isset($cache[$message_id]))
		return $cache[$message_id];

	$cache[$message_id] = array(
		'id_member' => 0,
		'posts' => 0,
	);

	if (empty($message_id))
		return $cache[$message_id];

	$request = $smcFunc['db_query']('', '
		SELECT IFNULL(m.id_member, 0) AS id_member, IFNULL(mem.posts, 0) AS posts
		FROM {db_prefix}messages AS m
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE m.id_msg = {int:id_msg}
		LIMIT 1',
		array(
			'id_msg' => $message_id,
		)
	);

	if ($smcFunc['db_num_rows']($request) !== 0)
	{
		$row = $smcFunc['db_fetch_assoc']($request);
		$cache[$message_id] = array(
			'id_member' => empty($row['id_member']) ? 0 : (int) $row['id_member'],
			'posts' => empty($row['posts']) ? 0 : (int) $row['posts'],
		);
	}

	$smcFunc['db_free_result']($request);

	return $cache[$message_id];
}

/**
 * Load a member's post count with a small request cache.
 *
 * @param int $member_id
 * @return int
 */
function antiSpamLinks_get_member_posts($member_id)
{
	global $smcFunc;
	static $cache = array(0 => 0);

	$member_id = (int) $member_id;
	if (isset($cache[$member_id]))
		return $cache[$member_id];

	$cache[$member_id] = 0;

	if (empty($member_id))
		return 0;

	$request = $smcFunc['db_query']('', '
		SELECT posts
		FROM {db_prefix}members
		WHERE id_member = {int:id_member}
		LIMIT 1',
		array(
			'id_member' => $member_id,
		)
	);

	if ($smcFunc['db_num_rows']($request) !== 0)
	{
		list ($cache[$member_id]) = $smcFunc['db_fetch_row']($request);
		$cache[$member_id] = (int) $cache[$member_id];
	}

	$smcFunc['db_free_result']($request);

	return $cache[$member_id];
}

/**
 * Determine whether posting external links should be blocked.
 *
 * @param int $poster_id
 * @param int $posts
 * @return bool
 */
function antiSpamLinks_should_block_links($poster_id, $posts)
{
	global $modSettings;

	$poster_id = (int) $poster_id;
	$posts = (int) $posts;

	if ($poster_id === 0)
		return !empty($modSettings['anti_spam_links_guests']) && (int) $modSettings['anti_spam_links_guests'] === 1;

	return !empty($modSettings['anti_spam_links_nolinks']) && $posts < (int) $modSettings['anti_spam_links_nolinks'];
}

/**
 * Get the appropriate error key for posting restrictions.
 *
 * @param int $poster_id
 * @return string
 */
function antiSpamLinks_get_block_error_key($poster_id)
{
	return (int) $poster_id === 0 ? 'anti_spam_links_nolinks_guest' : 'anti_spam_links_nolinks_member';
}

/**
 * Check whether a raw message contains at least one external link after BBC parsing.
 *
 * @param string $message
 * @return bool
 */
function antiSpamLinks_message_has_external_link($message)
{
	$parsed = parse_bbc($message, false);

	if ($parsed === '' || stripos($parsed, '<a ') === false)
		return false;

	if (!preg_match_all('~<a\b[^>]*\bhref="([^"]+)"[^>]*>~i', $parsed, $matches))
		return false;

	foreach ($matches[1] as $url)
		if (!antiSpamLinks_is_internal_url($url))
			return true;

	return false;
}

/**
 * Apply nonactive/nofollow behavior to parsed HTML.
 *
 * @param string $html
 * @param int $poster_id
 * @param int $posts
 * @return string
 */
function antiSpamLinks_apply_to_html($html, $poster_id, $posts)
{
	global $txt;

	$policy = antiSpamLinks_get_render_policy($poster_id, $posts);

	if ($policy['mode'] === 'none' || $html === '' || stripos($html, '<a ') === false)
		return $html;

	antiSpamLinks_load_text();

	if ($policy['mode'] === 'nonactive')
	{
		return preg_replace_callback(
			'~<a\b[^>]*\bhref="([^"]+)"[^>]*>.*?</a>~i',
			function ($matches) use ($policy, $txt) {
				$url = $matches[1];
				if (antiSpamLinks_is_internal_url($url))
					return $matches[0];

				return $txt['anti_spam_links_newbielink'] . $url . ' <span class="alert smalltext" title="' . $policy['title'] . '">' . $txt['anti_spam_links_nonactive'] . '</span>';
			},
			$html
		);
	}

	return preg_replace_callback(
		'~<a\b[^>]*\bhref="([^"]+)"[^>]*>.*?</a>~i',
		function ($matches) use ($policy, $txt) {
			$url = $matches[1];
			$link = $matches[0];

			if (antiSpamLinks_is_internal_url($url))
				return $link;

			if (preg_match('~\brel="([^"]*)"~i', $link, $rel_match))
			{
				$rels = preg_split('~\s+~', trim($rel_match[1]));
				if (!in_array('nofollow', $rels, true))
					$rels[] = 'nofollow';

				$link = preg_replace('~\brel="[^"]*"~i', 'rel="' . implode(' ', array_filter($rels)) . '"', $link, 1);
			}
			else
				$link = preg_replace('~<a\b~i', '<a rel="nofollow"', $link, 1);

			return $link . ' <span class="alert smalltext" title="' . $policy['title'] . '">' . $txt['anti_spam_links_nofollow'] . '</span>';
		},
		$html
	);
}

/**
 * Decide which rendering policy applies to a poster.
 *
 * @param int $poster_id
 * @param int $posts
 * @return array
 */
function antiSpamLinks_get_render_policy($poster_id, $posts)
{
	global $modSettings, $txt;

	antiSpamLinks_load_text();

	$poster_id = (int) $poster_id;
	$posts = (int) $posts;

	if ($poster_id === 0)
	{
		$guest_mode = empty($modSettings['anti_spam_links_guests']) ? 0 : (int) $modSettings['anti_spam_links_guests'];

		if ($guest_mode === 2)
			return array('mode' => 'nonactive', 'title' => $txt['anti_spam_links_guests_nonactive_info']);
		if ($guest_mode === 3)
			return array('mode' => 'nofollow', 'title' => $txt['anti_spam_links_guests_nofollow_info']);

		return array('mode' => 'none', 'title' => '');
	}

	if (!empty($modSettings['anti_spam_links_newbielinks']) && $posts < (int) $modSettings['anti_spam_links_newbielinks'])
		return array(
			'mode' => 'nonactive',
			'title' => sprintf($txt['anti_spam_links_newbielinks_info'], (int) $modSettings['anti_spam_links_newbielinks']),
		);

	if (!empty($modSettings['anti_spam_links_nofollowlinks']) && $posts < (int) $modSettings['anti_spam_links_nofollowlinks'])
		return array(
			'mode' => 'nofollow',
			'title' => sprintf($txt['anti_spam_links_nofollowlinks_info'], (int) $modSettings['anti_spam_links_nofollowlinks']),
		);

	return array('mode' => 'none', 'title' => '');
}

/**
 * Check whether a URL should be treated as internal to the forum.
 *
 * @param string $url
 * @return bool
 */
function antiSpamLinks_is_internal_url($url)
{
	global $boardurl;

	$url = trim(htmlspecialchars_decode((string) $url, ENT_QUOTES));

	if ($url === '')
		return true;

	if ($url[0] === '#' || $url[0] === '/')
		return true;

	if (strpos($url, './') === 0 || strpos($url, '../') === 0)
		return true;

	$normalized_boardurl = rtrim((string) $boardurl, '/');
	if ($normalized_boardurl !== '' && stripos($url, $normalized_boardurl) === 0)
		return true;

	$board_parts = @parse_url($boardurl);
	$url_parts = @parse_url($url);

	if (!empty($board_parts['host']) && !empty($url_parts['host']) && strtolower($board_parts['host']) === strtolower($url_parts['host']))
		return true;

	return false;
}

