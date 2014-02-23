<?php
/* ***** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is http://www.sa-mods.info
 *
 * The Initial Developer of the Original Code is
 * wayne Mankertz.
 * Portions created by the Initial Developer are Copyright (C) 2011
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *
 * ***** END LICENSE BLOCK ***** */
if (!defined('SMF'))
	die('Hacking attempt...');

function KB_knowcont()
{
	global $smcFunc, $txt, $scripturl, $sourcedir, $boardurl, $modSettings, $user_info, $context;

	$context['sub_template'] = 'kb_knowcont';

	if (isset($_REQUEST['cont']))
	{
		if (($listData = cache_get_data('kb_articles_listinfo'.$_GET['cont'].'', 3600)) === null)
		{
			$params = array(
				'table' => 'kb_articles AS a',
				'call' => 'a.title,a.kbnid,a.id_cat,c.name',
				'left_join' => '{db_prefix}kb_category AS c ON (a.id_cat = c.kbid)',
				'where' => 'a.kbnid = {int:kbnid}',
			);

			$data = array(
				'kbnid' => (int) $_GET['cont'],
			);

			$listData = KB_ListData($params, $data);
			cache_put_data('kb_articles_listinfo'.$_GET['cont'].'', $listData, 3600);
		}
		$artname = $listData['title'];
		$aid = $listData['kbnid'];
		$cid = $listData['id_cat'];
		$cname = $listData['name'];

		if (!$aid)
			fatal_error(''.$txt['kb_pinfi7'].' <strong>'.$_GET['cont'].'</strong> '.$txt['kb_jumpgo1'].'',false);

		$context['linktree'][] = array(
			'url' => $scripturl . '?action=kb;area=cats;cat='.$cid.'',
			'name' => $cname,
		);
		$context['linktree'][] = array(
			'url' => $scripturl . '?action=kb;area=article;cont='.$_GET['cont'].'',
			'name' => $artname,
		);

		if (($context['know'] = cache_get_data('kb_articles'.$_GET['cont'].'', 3600)) === null)
		{
			$result = $smcFunc['db_query']('', '
				SELECT k.kbnid,k.content, k.source, k.title,k.id_cat,k.date,k.id_member,m.real_name, k.views, k.rate, k.approved
				FROM {db_prefix}kb_articles AS k
					LEFT JOIN {db_prefix}members AS m ON (k.id_member = m.id_member)
					LEFT JOIN {db_prefix}attachments AS a ON (a.id_member = m.id_member)
				WHERE kbnid = {int:kbnid}',
				array(
					'kbnid' => (int) $_GET['cont'],
				)
			);
			$context['know'] = array();

			while ($row = $smcFunc['db_fetch_assoc']($result))
			{
				$context['know'][] = array(
					'content' => KB_parseTags($row['content'], $row['kbnid'], 3),
					'title' => parse_bbc($row['title']),
					'source' => parse_bbc($row['source']),
					'kbnid' => $row['kbnid'],
					'approved' => $row['approved'],
					'views' => $row['views'],
					'rate' => $row['rate'],
					'date' => date('D d M Y',$row['date']),
					'id_cat' => $row['id_cat'],
					'id_member' => $row['id_member'],
					'real_name' => $row['real_name'],
				);
			}
			$smcFunc['db_free_result']($result);
			cache_put_data('kb_articles'.$_GET['cont'].'', $context['know'], 3600);
		}

		$context['page_title'] = $context['know'][0]['title'];

		if ($context['know'][0]['approved'] == 0 && $context['know'][0]['id_member'] != $user_info['id'] && !allowedTo('manage_kb'))
			fatal_lang_error('kb_articlwnot_approved',false);

		KBisAllowedto($context['know'][0]['id_cat'],'view');

		$context['kbimg'] = KB_getimages($_GET['cont']);

		if (!empty($modSettings['kb_ecom']))
		{
			$context['kbcom'] = KB_getcomments($_GET['cont']);
			KB_showediter(!empty($_POST['description']) ? $_POST['description'] : '','description');
		}

		KB_dojprint();

		$query_params = array(
			'table' => 'kb_articles',
			'set' => 'views = views + 1',
			'where' => 'kbnid = {int:kbnid}',
		);

		$query_data = array(
			'kbnid' => (int) $_GET['cont'],
		);

		kb_UpdateData($query_params,$query_data);
	}
	if ($user_info['is_guest'])
	{
		require_once($sourcedir . '/Subs-Editor.php');
		$verificationOptions = array(
			'id' => 'register',
		);
		$context['visual_verification'] = create_control_verification($verificationOptions);
		$context['visual_verification_id'] = $verificationOptions['id'];
	}
	//comment
	if (isset($_REQUEST['comment']))
	{
		if ($user_info['is_guest'])
		{
			require_once($sourcedir . '/Subs-Editor.php');
			$verificationOptions = array(
				'id' => 'register',
			);

			$context['visual_verification'] = create_control_verification($verificationOptions, true);

			if (is_array($context['visual_verification']))
			{
				loadLanguage('Errors');
				foreach ($context['visual_verification'] as $error)
					fatal_error($txt['error_' . $error]);
			}
		}

		isAllowedTo('com_kb');
		checkSession();

		$_POST['description'] = $smcFunc['htmlspecialchars']($_POST['description'], ENT_QUOTES);
		$_GET['arid'] = (int) $_GET['arid'];

		if (empty($_POST['description']))
			fatal_lang_error('knowledgebase_emtydesc',false);

		$approved = allowedTo('auto_approvecom_kb') ? 1 : 0;

		$mes = ''.$txt['kb_log_text4'].' <strong><a href="'.$scripturl.'?action=kb;area=article;cont='.$_GET['arid'].'">'. $context['know'][0]['title'].'</a></strong>';
		KB_log_actions('add_com',$_GET['arid'], $mes);

		$data = array(
			'table' => 'kb_comments',
			'cols' => array('id_article' => 'int', 'comment' => 'string', 'date' => 'int', 'id_member' => 'int','approved' => 'int'),
		);
		$values = array(
			$_GET['arid'],
			$_POST['description'],
			time(),
			$user_info['id'],
			$approved
		);

		$indexes = array(
			'id_article'
		);
		KB_InsertData($data, $values, $indexes);

		KBrecountcomments();
		KB_cleanCache();
		redirectexit('action=kb;area=article;cont='.$_GET['arid'].'');
	}

	if (isset($_REQUEST['commentdel']))
	{
		isAllowedTo('comdel_kb');

		$mes = ''.$txt['kb_log_text3'].' <strong><a href="'.$scripturl.'?action=kb;area=article;cont='.$_GET['cont'].'">'. $context['know'][0]['title'].'</a></strong>';
		KB_log_actions('del_com',$_GET['cont'], $mes);

		$query_params = array(
			'table' => 'kb_comments',
			'where' => 'id = {int:kbid}',
		);

		$query_data = array(
			'kbid' => (int) $_GET['arid'],
		);

		KB_DeleteData($query_params,$query_data);
		KB_cleanCache();
		KBrecountcomments();
		redirectexit('action=kb;area=article;cont='.$_GET['cont'].'');
	}

	//approve
	if (isset($_REQUEST['approve']))
	{
		checkSession('get');

		$query_params = array(
			'table' => 'kb_articles',
			'set' => 'approved = {int:one}',
			'where' => 'kbnid = {int:kbnid}',
		);

		$query_data = array(
			'kbnid' => (int) $_REQUEST['aid'],
			'one' => 1,
		);

		kb_UpdateData($query_params,$query_data);

		$params = array(
			'table' => 'kb_articles',
			'call' => 'id_member, kbnid, title',
			'where' => 'kbnid = {int:kbnid}',
		);

		$data = array(
			'kbnid' => (int) $_GET['aid'],
		);

		$listData = KB_ListData($params, $data);
		$nameid = $listData['id_member'];
		$kid = $listData['kbnid'];
		$title = $listData['title'];

		$kbmes = ''.$txt['kb_aapprove1'].' [url='.$scripturl.'?action=kb;area=article;cont='.$kid.']'.$txt['kb_aapprove2'].'[/url] '.$txt['kb_aapprove3'].'';
		KB_sendpm($nameid,$txt['kb_aapprove6'],$kbmes);

		$mes = ''.$txt['kb_log_text2'].' <strong><a href="'.$scripturl.'?action=kb;area=article;cont='.$kid.'">'. $title.'</a></strong>';
		KB_log_actions('app_article',$kid, $mes);
		KBrecountItems();
		KB_cleanCache();

		redirectexit('action=kb;area=article;cont='.$_REQUEST['aid'].'');
	}
	//unapprove
	if (isset($_REQUEST['unapprove']) && isset($_REQUEST['inap']))
	{
		checkSession('get');

		$query_params = array(
			'table' => 'kb_articles',
			'set' => 'approved = {int:one}',
			'where' => 'kbnid = {int:kbnid}',
		);

		$query_data = array(
			'kbnid' => (int) $_REQUEST['inap'],
			'one' => 0,
		);

		kb_UpdateData($query_params,$query_data);

		$params = array(
			'table' => 'kb_articles',
			'call' => 'id_member, kbnid, title',
			'where' => 'kbnid = {int:kbnid}',
		);

		$data = array(
			'kbnid' => (int) $_GET['inap'],
		);

		$listData = KB_ListData($params, $data);
		$nameid = $listData['id_member'];
		$kid = $listData['kbnid'];
		$title = $listData['title'];

		$kbmes = ''.$txt['kb_aapprove4'].' [url='.$scripturl.'?action=kb;area=article;cont='.$kid.']'.$txt['kb_aapprove2'].'[/url] '.$txt['kb_aapprove3'].'';
		KB_sendpm($nameid,$txt['kb_aapprove7'],$kbmes);

		$mes = ''.$txt['kb_log_text1'].' <strong><a href="'.$scripturl.'?action=kb;area=article;cont='.$kid.'">'. $title.'</a></strong>';
			KB_log_actions('unapp_article',$kid, $mes);
		KBrecountItems();
		KB_cleanCache();

		redirectexit('action=kb;area=article;cont='.$_REQUEST['inap'].'');
	}
}

function kb_dowloadAttach($id)
{
	global $smcFunc, $modSettings, $txt, $context;

	if (empty($id))
		return false;

	$file = KB_getimage($id);

	$filename = $modSettings['kb_path_attachment'] . $file['hash'] . '.kb';
	$real_filename = $file['filename'];

	// This is done to clear any output that was made before now. (would use ob_clean(), but that's PHP 4.2.0+...)
	ob_end_clean();
	ob_start();
	header('Content-Encoding: none');

	// No point in a nicer message, because this is supposed to be an attachment anyway...
	if (!file_exists($filename))
	{
		loadLanguage('Errors');

		header('HTTP/1.0 404 ' . $txt['attachment_not_found']);
		header('Content-Type: text/plain; charset=' . (empty($context['character_set']) ? 'ISO-8859-1' : $context['character_set']));

		// We need to die like this *before* we send any anti-caching headers as below.
		die('404 - ' . $txt['attachment_not_found']);
	}

	$validImageTypes = array(
		1 => 'gif',
		2 => 'jpeg',
		3 => 'png',
		5 => 'psd',
		6 => 'bmp',
		7 => 'tiff',
		8 => 'tiff',
		9 => 'jpeg',
		14 => 'iff'
	);

	if (!empty($file['is_image']))
	{
		$size = @getimagesize($filename);
		list ($width, $height) = $size;

		// If it's an image get the mime type right.
		if ($width)
		{
			// Got a proper mime type?
			if (!empty($size['mime']))
				$mime_type = $size['mime'];
			// Otherwise a valid one?
			elseif (isset($validImageTypes[$size[2]]))
				$mime_type = 'image/' . $validImageTypes[$size[2]];
		}
	}
	$file_ext = substr($real_filename, -(strlen($real_filename) - strpos($real_filename, '.') - 1), strlen($real_filename) - strpos($real_filename, '.'));

	// If it hasn't been modified since the last time this attachement was retrieved, there's no need to display it again.
	if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']))
	{
		list($modified_since) = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
		if (strtotime($modified_since) >= filemtime($filename))
		{
			ob_end_clean();

			// Answer the question - no, it hasn't been modified ;).
			header('HTTP/1.1 304 Not Modified');
			exit;
		}
	}

	// Check whether the ETag was sent back, and cache based on that...
	$eTag = '"' . substr($id . $real_filename . filemtime($filename), 0, 64) . '"';
	if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && strpos($_SERVER['HTTP_IF_NONE_MATCH'], $eTag) !== false)
	{
		ob_end_clean();

		header('HTTP/1.1 304 Not Modified');
		exit;
	}

	// Send the attachment headers.
	header('Pragma: ');
	if (!$context['browser']['is_gecko'])
		header('Content-Transfer-Encoding: binary');
	header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filename)) . ' GMT');
	header('Accept-Ranges: bytes');
	header('Connection: close');
	header('ETag: ' . $eTag);

	// IE 6 just doesn't play nice. As dirty as this seems, it works.
	if ($context['browser']['is_ie6'] && isset($_REQUEST['image']))
		unset($_REQUEST['image']);

	// Make sure the mime type warrants an inline display.
	elseif (isset($_REQUEST['image']) && !empty($mime_type) && strpos($mime_type, 'image/') !== 0)
		unset($_REQUEST['image']);

	// Does this have a mime type?
	elseif (!empty($mime_type) && (isset($_REQUEST['image']) || !in_array($file_ext, array('jpg', 'gif', 'jpeg', 'x-ms-bmp', 'png', 'psd', 'tiff', 'iff'))))
		header('Content-Type: ' . strtr($mime_type, array('image/bmp' => 'image/x-ms-bmp')));

	else
	{
		header('Content-Type: ' . ($context['browser']['is_ie'] || $context['browser']['is_opera'] ? 'application/octetstream' : 'application/octet-stream'));
		if (isset($_REQUEST['image']))
			unset($_REQUEST['image']);
	}

	// Convert the file to UTF-8, cuz most browsers dig that.
	$utf8name = !$context['utf8'] && function_exists('iconv') ? iconv($context['character_set'], 'UTF-8', $real_filename) : (!$context['utf8'] && function_exists('mb_convert_encoding') ? mb_convert_encoding($real_filename, 'UTF-8', $context['character_set']) : $real_filename);
	$fixchar = create_function('$n', '
		if ($n < 32)
			return \'\';
		elseif ($n < 128)
			return chr($n);
		elseif ($n < 2048)
			return chr(192 | $n >> 6) . chr(128 | $n & 63);
		elseif ($n < 65536)
			return chr(224 | $n >> 12) . chr(128 | $n >> 6 & 63) . chr(128 | $n & 63);
		else
			return chr(240 | $n >> 18) . chr(128 | $n >> 12 & 63) . chr(128 | $n >> 6 & 63) . chr(128 | $n & 63);');

	$disposition = !isset($_REQUEST['image']) ? 'attachment' : 'inline';

	// Different browsers like different standards...
	if ($context['browser']['is_firefox'])
		header('Content-Disposition: ' . $disposition . '; filename*="UTF-8\'\'' . preg_replace('~&#(\d{3,8});~e', '$fixchar(\'$1\')', $utf8name) . '"');

	elseif ($context['browser']['is_opera'])
		header('Content-Disposition: ' . $disposition . '; filename="' . preg_replace('~&#(\d{3,8});~e', '$fixchar(\'$1\')', $utf8name) . '"');

	elseif ($context['browser']['is_ie'])
		header('Content-Disposition: ' . $disposition . '; filename="' . urlencode(preg_replace('~&#(\d{3,8});~e', '$fixchar(\'$1\')', $utf8name)) . '"');

	else
		header('Content-Disposition: ' . $disposition . '; filename="' . $utf8name . '"');

	// If this has an "image extension" - but isn't actually an image - then ensure it isn't cached cause of silly IE.
	if (!isset($_REQUEST['image']) && in_array($file_ext, array('gif', 'jpg', 'bmp', 'png', 'jpeg', 'tiff')))
		header('Cache-Control: no-cache');
	else
		header('Cache-Control: max-age=' . (525600 * 60) . ', private');

	header('Content-Length: ' . filesize($filename));

	// Try to buy some time...
	@set_time_limit(600);

	// Since we don't do output compression for files this large...
	if (filesize($filename) > 4194304)
	{
		// Forcibly end any output buffering going on.
		if (function_exists('ob_get_level'))
		{
			while (@ob_get_level() > 0)
				@ob_end_clean();
		}
		else
		{
			@ob_end_clean();
			@ob_end_clean();
			@ob_end_clean();
		}

		$fp = fopen($filename, 'rb');
		while (!feof($fp))
		{
			if (isset($callback))
				echo $callback(fread($fp, 8192));
			else
				echo fread($fp, 8192);
			flush();
		}
		fclose($fp);
	}
	// On some of the less-bright hosts, readfile() is disabled.  It's just a faster, more byte safe, version of what's in the if.
	elseif (isset($callback) || readfile($filename) == null)
		echo isset($callback) ? $callback(file_get_contents($filename)) : file_get_contents($filename);

	obExit(false);
}
?>