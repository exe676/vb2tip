<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.2.0 
|| # ---------------------------------------------------------------- # ||
|| # Copyright �2000-2012 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

// #############################################################################
/**
* Returns the maximum compatible editor mode depending on permissions, options and browser
*
* @param	integer	The requested editor mode (-1 = user default, 0 = simple textarea, 1 = standard editor controls, 2 = wysiwyg controls)
* @param	string	Editor type (full = 'fe', quick reply = 'qr')
*
* @return	integer	The maximum possible mode (0, 1, 2)
*/
function is_wysiwyg_compatible($userchoice = -1, $editormode = 'fe')
{
	global $vbulletin;

	if (STYLE_TYPE == 'mobile' AND (!defined('VB_API') OR VB_API !== true))
	{
		return 0;
	}

	// check for a standard setting
	if ($userchoice == -1)
	{
		$userchoice = $vbulletin->userinfo['showvbcode'];
	}

	// unserialize the option if we need to
	if (!is_array($vbulletin->options['editormodes_array']))
	{
		$vbulletin->options['editormodes_array'] = unserialize($vbulletin->options['editormodes']);
	}

	// make sure we have a valid editor mode to check
	switch ($editormode)
	{
		case 'fe':
		case 'qr':
		case 'qe':
			break;
		case 'qr_small':
			$editormode = 'qr';
			break;
		default:
			$editormode = 'fe';
	}

	// check board options for toolbar permissions
	if ($userchoice > $vbulletin->options['editormodes_array']["$editormode"])
	{
		$choice = $vbulletin->options['editormodes_array']["$editormode"];
	}
	else
	{
		$choice = $userchoice;
	}

	$hook_return = null;
	($hook = vBulletinHook::fetch_hook('editor_wysiwyg_compatible')) ? eval($hook) : false;
	if ($hook_return !== null)
	{
		return $hook_return;
	}

	return $choice;
}

// #############################################################################
/**
* Prepares the templates for a message editor
*
* @param	string	The text to be initially loaded into the editor
* @param	boolean	Is the initial text HTML (rather than plain text or bbcode)?
* @param	mixed	Forum ID of the forum into which we are posting. Special rules apply for values of 'privatemessage', 'usernote', 'calendar', 'announcement' and 'nonforum'.
* @param	boolean	Allow smilies?
* @param	boolean	Parse smilies in the text of the message?
* @param	boolean	Allow attachments?
* @param	string	Editor type - either 'fe' for full editor or 'qr' for quick reply
* @param	string	Force the editor to use the specified value as its editorid, rather than making one up
* @param	array	Information for the image popup
* @param	array	Content type handled by this editor, used to set specific CSS
* @param	string	String value of this content type, e.g. vBForum_Post
* @param	int		Contentid of this item, if it exists already
* @param	int		Parent contentid of this item
* @param	bool	Is this a preview text? Don't autoload if it is
* @param	bool	Don't autoload, regardless ..
* @param	string	Autoload title..
* @param	string	HTML id of title field
*
* @return	string	Editor ID
*/
function construct_edit_toolbar(
	$text = '',
	$ishtml = false,
	$forumid = 0,
	$allowsmilie = true,
	$parsesmilie = true,
	$can_attach = false,
	$editor_type = 'fe',
	$force_editorid = '',
	$attachinfo = array(),
	$content = 'content',
	$contenttypeid = '',
	$contentid = 0,
	$parentcontentid = 0,
	$preview = false,
	$autoload = true,
	$autoloadtitleid = ''
)
{
	// standard stuff
	global $vbulletin, $vbphrase, $show;
	// templates generated by this function
	global $messagearea, $smiliebox, $disablesmiliesoption, $checked, $vBeditTemplate;

	// counter for editorid
	static $editorcount = 0;

	// determine what we can use
	// this was moved up here as I need the switch to determine if bbcode is enabled
	// to determine if a toolbar is usable
	if ($forumid == 'signature')
	{
		$sig_perms =& $vbulletin->userinfo['permissions']['signaturepermissions'];
		$sig_perms_bits =& $vbulletin->bf_ugp_signaturepermissions;

		$can_toolbar = ($sig_perms & $sig_perms_bits['canbbcode']) ? true : false;

		$show['img_bbcode']   = ($sig_perms & $sig_perms_bits['allowimg']) ? true : false;
		$show['video_bbcode'] = ($sig_perms & $sig_perms_bits['allowvideo']) ? true : false;
		$show['font_bbcode']  = ($sig_perms & $sig_perms_bits['canbbcodefont'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_FONT) ? true : false;
		$show['size_bbcode']  = ($sig_perms & $sig_perms_bits['canbbcodesize'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_SIZE) ? true : false;
		$show['color_bbcode'] = ($sig_perms & $sig_perms_bits['canbbcodecolor'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_COLOR) ? true : false;
		$show['basic_bbcode'] = ($sig_perms & $sig_perms_bits['canbbcodebasic'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_BASIC) ? true : false;
		$show['align_bbcode'] = ($sig_perms & $sig_perms_bits['canbbcodealign'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_ALIGN) ? true : false;
		$show['list_bbcode']  = ($sig_perms & $sig_perms_bits['canbbcodelist'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_LIST) ? true : false;
		$show['code_bbcode']  = ($sig_perms & $sig_perms_bits['canbbcodecode'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_CODE) ? true : false;
		$show['html_bbcode']  = ($sig_perms & $sig_perms_bits['canbbcodehtml'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_HTML) ? true : false;
		$show['php_bbcode']   = ($sig_perms & $sig_perms_bits['canbbcodephp'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_PHP) ? true : false;
		$show['url_bbcode']   = ($sig_perms & $sig_perms_bits['canbbcodelink'] AND $vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_URL) ? true : false;
		$show['quote_bbcode'] = ($sig_perms & $sig_perms_bits['canbbcodequote']) ? true : false;
	}
	else
	{
		require_once(DIR . '/includes/class_bbcode.php');
		$show['font_bbcode']  = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_FONT)  ? true : false;
		$show['size_bbcode']  = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_SIZE)  ? true : false;
		$show['color_bbcode'] = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_COLOR) ? true : false;
		$show['basic_bbcode'] = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_BASIC) ? true : false;
		$show['align_bbcode'] = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_ALIGN) ? true : false;
		$show['list_bbcode']  = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_LIST)  ? true : false;
		$show['code_bbcode']  = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_CODE)  ? true : false;
		$show['html_bbcode']  = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_HTML)  ? true : false;
		$show['php_bbcode']   = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_PHP)   ? true : false;
		$show['url_bbcode']   = ($vbulletin->options['allowedbbcodes'] & ALLOW_BBCODE_URL)   ? true : false;
		$show['quote_bbcode'] = true; // can't disable this anywhere but in sigs
	}

	$ajax_extra = array();

	$show['custom_bbcode'] = $allow_custom_bbcode = true;

	if (empty($forumid))
	{
		$forumid = 'nonforum';
	}
	switch($forumid)
	{
		case 'privatemessage':
			$can_toolbar = $vbulletin->options['privallowbbcode'];
			$show['img_bbcode'] = $vbulletin->options['privallowbbimagecode'];
			$show['video_bbcode'] = $vbulletin->options['privallowbbvideocode'];
			break;

		case 'usernote':
			$can_toolbar = $vbulletin->options['unallowvbcode'];
			$show['img_bbcode'] = $vbulletin->options['unallowimg'];
			$show['video_bbcode'] = $vbulletin->options['unallowvideo'];
			break;

		case 'calendar':
			global $calendarinfo;
			$can_toolbar = $calendarinfo['allowbbcode'];
			$show['img_bbcode'] = $calendarinfo['allowimgcode'];
			$show['video_bbcode'] = $calendarinfo['allowvideocode'];
			$ajax_extra['calendarid'] = $calendarinfo['calendarid'];
			break;

		case 'announcement':
			$can_toolbar = true;
			$show['img_bbcode'] = true;
			$show['video_bbcode'] = true;
			break;

		case 'signature':
			// see above -- these are handled earlier
			break;

		case 'visitormessage':
		case 'groupmessage':
		case 'picturecomment':
		{
			switch($forumid)
			{
				case 'groupmessage':
					$allowedoption = $vbulletin->options['sg_allowed_bbcode'];
				break;

				case 'picturecomment':
					$allowedoption = $vbulletin->options['pc_allowed_bbcode'];
				break;

				default:
					$allowedoption = $vbulletin->options['vm_allowed_bbcode'];
				break;
			}

			$show['font_bbcode']  = ($show['font_bbcode']  AND $allowedoption & ALLOW_BBCODE_FONT)  ? true : false;
			$show['size_bbcode']  = ($show['size_bbcode']  AND $allowedoption & ALLOW_BBCODE_SIZE)  ? true : false;
			$show['color_bbcode'] = ($show['color_bbcode'] AND $allowedoption & ALLOW_BBCODE_COLOR) ? true : false;
			$show['basic_bbcode'] = ($show['basic_bbcode'] AND $allowedoption & ALLOW_BBCODE_BASIC) ? true : false;
			$show['align_bbcode'] = ($show['align_bbcode'] AND $allowedoption & ALLOW_BBCODE_ALIGN) ? true : false;
			$show['list_bbcode']  = ($show['list_bbcode']  AND $allowedoption & ALLOW_BBCODE_LIST)  ? true : false;
			$show['code_bbcode']  = ($show['code_bbcode']  AND $allowedoption & ALLOW_BBCODE_CODE)  ? true : false;
			$show['html_bbcode']  = ($show['html_bbcode']  AND $allowedoption & ALLOW_BBCODE_HTML)  ? true : false;
			$show['php_bbcode']   = ($show['php_bbcode']   AND $allowedoption & ALLOW_BBCODE_PHP)   ? true : false;
			$show['url_bbcode']   = ($show['url_bbcode']   AND $allowedoption & ALLOW_BBCODE_URL)   ? true : false;
			$show['quote_bbcode'] = ($show['quote_bbcode'] AND $allowedoption & ALLOW_BBCODE_QUOTE) ? true : false;
			$show['img_bbcode']   = ($allowedoption & ALLOW_BBCODE_IMG) ? true : false;
			$show['video_bbcode'] = ($allowedoption & ALLOW_BBCODE_VIDEO) ? true : false;

			$can_toolbar = (
				$show['font_bbcode'] OR $show['size_bbcode'] OR $show['color_bbcode'] OR
				$show['basic_bbcode'] OR $show['align_bbcode'] OR $show['list_bbcode'] OR
				$show['code_bbcode'] OR $show['html_bbcode'] OR $show['php_bbcode'] OR
				$show['url_bbcode'] OR $show['quote_bbcode'] OR $show['img_bbcode'] OR
				$show['video_bbcode']
			);

			$show['custom_bbcode'] = $allow_custom_bbcode = ($allowedoption & ALLOW_BBCODE_CUSTOM ? true : false);
		}
		break;

		case 'nonforum':
			$can_toolbar = $vbulletin->options['allowbbcode'];
			$show['img_bbcode'] = $vbulletin->options['allowbbimagecode'];
			$show['video_bbcode'] = $vbulletin->options['allowbbvideocode'];
			break;

		case 'article_comment':
			// Right here there should be comment specific settings ... but wait, when you edit the comment, you actually
			// go through the post editor so that would be different settings .. confused?
		case 'article':
			// Cms always defaults $can_toolbar and $show['img_bbcode'] and $show['video_bbcode'] to true.. uh..
			if ($vbulletin->options['vbcmsforumid'] > 0)
			{
				$forum = fetch_foruminfo($vbulletin->options['vbcmsforumid']);
				$can_toolbar = $forum['allowbbcode'];
				$show['img_bbcode'] = $forum['allowimages'];
				$show['video_bbcode'] = $forum['allowvideos'];
			}
			else
			{
				$can_toolbar = $show['img_bbcode'] = $show['video_bbcode'] = true;
			}
			break;

		default:
			if (intval($forumid))
			{
				$forum = fetch_foruminfo($forumid);
				$can_toolbar = $forum['allowbbcode'];
				$show['img_bbcode'] = $forum['allowimages'];
				$show['video_bbcode'] = $forum['allowvideos'];
			}
			else
			{
				$can_toolbar = false;
				$show['img_bbcode'] = false;
				$show['video_bbcode'] = false;
			}

			($hook = vBulletinHook::fetch_hook('editor_toolbar_switch')) ? eval($hook) : false;
			break;
	}

	if (!$can_toolbar)
	{
		$show['font_bbcode'] = $show['size_bbcode'] = $show['color_bbcode'] =
		$show['basic_bbcode'] = $show['align_bbcode'] = $show['list_bbcode'] =
		$show['code_bbcode'] = $show['html_bbcode'] = $show['php_bbcode'] =
		$show['url_bbcode'] = $show['quote_bbcode'] = $show['img_bbcode'] =
		$show['video_bbcode']
		= false;
	}

	$editor_template_name = 'editor_ckeditor';
	// set the editor mode
	switch ($editor_type)
	{
		case 'qr':
			if ($force_editorid == '')
			{
				$editorid = 'vB_Editor_QR';
			}
			else
			{
				$editorid = $force_editorid;
			}

			$default_height = 100;
			$autofocus = false;
			break;

		case 'qr_small':
			if ($force_editorid == '')
			{
				$editorid = 'vB_Editor_QR';
			}
			else
			{
				$editorid = $force_editorid;
			}

			$default_height = 60;
			$autofocus = false;
			break;

		case 'qr_pm':
			if ($force_editorid == '')
			{
				$editorid = 'vB_Editor_QR';
			}
			else
			{
				$editorid = $force_editorid;
			}

			$default_height = 120;
			$autofocus = false;
			$editor_type = 'qr';
			break;

		case 'qe':
			if ($force_editorid == '')
			{
				$editorid = 'vB_Editor_QE';
			}
			else
			{
				$editorid = $force_editorid;
			}

			$default_height = 200;

			$editor_template_name = 'postbit_quickedit';
			$autofocus = true;
			break;

		default:
			if ($force_editorid == '')
			{
				$editorid = 'vB_Editor_' . str_pad(++$editorcount, 3, 0, STR_PAD_LEFT);
			}
			else
			{
				$editorid = $force_editorid;
			}
			$default_height = 250;
			if ($editor_type == 'fe_nofocus')
			{
				$autofocus = false;
				$editor_type = 'fe';
			}
			else
			{
				$autofocus = false;
				//$autofocus = true;
			}
			break;
	}

	// set the editor mode
	if (isset($_REQUEST['wysiwyg']))
	{
		// 2 = wysiwyg; 1 = standard
		if ($_REQUEST['wysiwyg'])
		{
			$vbulletin->userinfo['showvbcode'] = 2;
		}
		else if ($vbulletin->userinfo['showvbcode'] == 0)
		{
			$vbulletin->userinfo['showvbcode'] = 0;
		}
		else
		{
			$vbulletin->userinfo['showvbcode'] = 1;
		}
	}
	$toolbartype = is_wysiwyg_compatible(-1, $editor_type);

	$show['wysiwyg_compatible'] = (is_wysiwyg_compatible(2, $editor_type) == 2);
	$show['editor_toolbar'] = ($toolbartype > 0);

	// set the height of the editor based on the editor_height cookie if it exists
	$editor_height = $vbulletin->input->clean_gpc('c', 'editor_height', TYPE_NOHTML);
	$editor_heights = explode(':', $editor_height);
	foreach ($editor_heights AS $items)
	{
		$item = explode('#', $items);
		if ($item[0] == $editor_type)
		{
			$editor_height = $item[1];
		}
	}
	$editor_height = ($editor_height > 100) ? $editor_height : $default_height;

	// init the variables used by the templates built by this function
	$vBeditJs = array(
		'normalmode'         => 'false'
	);
	$vBeditTemplate = array(
		'clientscript'       => '',
		'fontfeedback'       => '',
		'sizefeedback'       => '',
		'smiliepopup'        => ''
	);
	$extrabuttons = '';

	// initialize the ckeditor
	$contentid = intval($contentid);
	$parentcontentid = intval($parentcontentid);
	$autoloadtext = '';
	$autoloadtitle = '';

	if ($autoload AND $contenttypeid AND $vbulletin->userinfo['userid'] AND !$preview)
	{
		$autoloadinfo = $vbulletin->db->query_first("
			SELECT
				pagetext, title
			FROM " . TABLE_PREFIX . "autosave
			WHERE
				contentid = $contentid
					AND
				parentcontentid = $parentcontentid
					AND
				contenttypeid = '" . $vbulletin->db->escape_string($contenttypeid) . "'
					AND
				userid = {$vbulletin->userinfo['userid']}
		");
		if ($autoloadinfo AND $autoloadinfo['pagetext'] != $text)
		{
			$autoloadtext = $autoloadinfo['pagetext'];
			$autoloadtitle = $autoloadinfo['title'];
		}
	}

	($hook = vBulletinHook::fetch_hook('editor_toolbar_start')) ? eval($hook) : false;

	// show a post editing toolbar of some sort
	if ($show['editor_toolbar'])
	{
		if ($can_attach)
		{
			$show['attach'] = true;
		}

		// get extra buttons... experimental at the moment
		$extrabuttons = construct_editor_extra_buttons($editorid, $allow_custom_bbcode);

		$result = process_toolbar_text($text, $toolbartype, $ishtml, $forumid, $allowsmilie, $parsesmilie);
		$newpost['message'] = $result['message'];
		$newpost['message_bbcode'] = $result['bbcode'];

		$result = process_toolbar_text($autoloadtext, $toolbartype, $ishtml, $forumid, $allowsmilie, $parsesmilie);
		$newpost['message_autoload'] = $result['message'];
		$newpost['message_autoload_bbcode'] = $result['bbcode'];
	}
	else
	{
		// do not show a post editing toolbar
		$newpost = array(
			'message'          => $text,
			'message_autoload' => ''
		);
	}

	$cke = vB_Ckeditor::getInstance($editorid, $editor_type, $contenttypeid, $contentid, $parentcontentid, $vbulletin->userinfo['userid'], $toolbartype);

	foreach(array('editor_jsoptions_font', 'editor_jsoptions_size') AS $template)
	{
		$templater = vB_Template::create($template);
		$string = $templater->render(true);
		$fonts = preg_split('#\r?\n#s', $string, -1, PREG_SPLIT_NO_EMPTY);
		foreach($fonts AS $fontkey => $fontsize)
		{
			$fonts[$fontkey] = trim($fontsize);
		}

		if ($template == 'editor_jsoptions_font')
		{
			$cke->addFonts($fonts);
		}
		else
		{
			$cke->addFontSizes($fonts);
		}
	}

	// set editor height
	$cke->setEditorHeight($editor_height);
	$cke->setEditorType($editor_type);
	$cke->setEditorAutoFocus($autofocus);
	$cke->setEditorParsetype($forumid);
	$cke->setAutoLoad(unhtmlspecialchars($autoloadtext), unhtmlspecialchars($autoloadtitle), $autoloadtitleid);
	$cke->setContentFontType($content);
	if (!$can_toolbar)
	{
		$cke->setNoBbcode();
	}

	// disable smilies option and clickable smilie
	$show['smiliebox'] = false;
	$smiliebox = '';
	$smiliepopup = '';
	$disablesmiliesoption = '';

	if ($allowsmilie AND $show['editor_toolbar'])
	{
		switch ($editor_type)
		{
			case 'qr':
			case 'qr_small':
			case 'qr_pm':
			case 'qe':
				$usesmiliebox = false;
				break;
			default:
				$usesmiliebox = true;
		}

		// deal with disable smilies option
		if (!isset($checked['disablesmilies']))
		{
			$vbulletin->input->clean_gpc('r', 'disablesmilies', TYPE_BOOL);
			$checked['disablesmilies'] = iif($vbulletin->GPC['disablesmilies'], 'checked="checked"');
		}
		$templater = vB_Template::create('newpost_disablesmiliesoption');
			$templater->register('checked', $checked);
		$disablesmiliesoption = $templater->render();

		if ($toolbartype AND ($vbulletin->options['smtotal'] > 0 OR $vbulletin->options['wysiwyg_smtotal'] > 0))
		{
			// query smilies
			$smilies = $vbulletin->db->query_read_slave("
				SELECT smilieid, smilietext, smiliepath, smilie.title,
					imagecategory.title AS category
				FROM " . TABLE_PREFIX . "smilie AS smilie
				LEFT JOIN " . TABLE_PREFIX . "imagecategory AS imagecategory USING(imagecategoryid)
				ORDER BY imagecategory.displayorder, imagecategory.title, smilie.displayorder
			");

			// get total number of smilies
			$totalsmilies = $vbulletin->db->num_rows($smilies);

			if ($totalsmilies > 0)
			{
				if ($vbulletin->options['wysiwyg_smtotal'] > 0)
				{
					$show['wysiwygsmilies'] = true;

					// smilie dropdown menu
					$i = 0;
					while ($smilie = $vbulletin->db->fetch_array($smilies))
					{
						$cke->addSmilie($smilie);
						if ($prevcategory != $smilie['category'])
						{
							$prevcategory = $smilie['category'];
							$templater = vB_Template::create('editor_smilie_category');
								$templater->register('smilie', $smilie);
							$smiliepopup .= $templater->render();
						}
						if (++$i < $vbulletin->options['wysiwyg_smtotal'])
						{
							$templater = vB_Template::create('editor_smilie_row');
								$templater->register('smilie', $smilie);
							$smiliepopup .= $templater->render();
						}
						else
						{
							$show['moresmilies'] = true;
							break;
						}
					}
				}
				else
				{
					$show['wysiwygsmilies'] = false;
				}

				// clickable smilie box
				if ($vbulletin->options['smtotal'] AND $usesmiliebox)
				{
					$vbulletin->db->data_seek($smilies, 0);
					$i = 0;
					$smiliebits = '';
					while ($smilie = $vbulletin->db->fetch_array($smilies) AND $i++ < $vbulletin->options['smtotal'])
					{
						$templater = vB_Template::create('editor_smilie');
							$templater->register('smilie', $smilie);
							$templater->register('editorid', $editorid);
						$smiliebits .= $templater->render();
					}

					$show['moresmilieslink'] = ($totalsmilies > $vbulletin->options['smtotal']);
					$show['smiliebox'] = true;
				}

				$vbulletin->db->free_result($smilies);
			}

			if ($totalsmilies > $vbulletin->options['wysiwyg_smtotal'])
			{
				$cke->config['moresmilies'] = 1;
			}
		}
		if ($vbulletin->options['smtotal'] > 0 AND $usesmiliebox)
		{
			$templater = vB_Template::create('editor_smiliebox');
				$templater->register('editorid', $editorid);
				$templater->register('smiliebits', $smiliebits);
				$templater->register('totalsmilies', $totalsmilies);
			$smiliebox = $templater->render();
		}
		else
		{
			$smiliebox = '';
		}
	}

	($hook = vBulletinHook::fetch_hook('editor_toolbar_end')) ? eval($hook) : false;
	
	// Don't send the editor clientscript on ajax returns of the editor -- it won't do anything..
	if (!$_POST['ajax'])
	{
		$templater = vB_Template::create('editor_clientscript');
			$templater->register('vBeditJs', $vBeditJs);
		$vBeditTemplate['clientscript'] = $templater->render();
	}

	if ($ajax_extra)
	{
		$cke->setAjaxExtra($ajax_extra);
	}

	$show['is_wysiwyg_editor'] = intval($toolbartype == 2 ? 1 : 0);

	$cke->setAttachInfo($attachinfo);
	
	$cke->setShow($show);

	$ckeditor = $cke->getEditor($editorid, unhtmlspecialchars($text));
	$templater = vB_Template::create($editor_template_name);
		$templater->register('editorid', $editorid);
		$templater->register('editortype', $toolbartype == 2 ? 1 : 0);
		$templater->register('smiliebox', $smiliebox);
		$templater->register('ckeditor', $ckeditor);
		$templater->register('newpost', $newpost);
	$messagearea = $vBeditTemplate['clientscript'] . $templater->render();

	return $editorid;
}

// #############################################################################
/**
* Returns process pagetext
*
* @param	string	Text to process
*
* @return	array	text and bbcode, bbcode is used by VB_API
*/
function process_toolbar_text($text, $toolbartype, $ishtml, $forumid, $allowsmilie, $parsesmilie)
{
	global $vbulletin;
	
	$result = array(
		'message' => '',
		'bbcode'  => ''
	);

	if (!$text)
	{
		return $result;
	}

	if ($toolbartype == 2 OR (defined('VB_API') AND VB_API === true))
	{
		// got to parse the message to be displayed from bbcode into HTML
		if ($text !== '')
		{
			require_once(DIR . '/includes/class_wysiwygparser.php');
			$html_parser = new vB_WysiwygHtmlParser($vbulletin);
			$result['message'] = $html_parser->parse_wysiwyg_html($text, $ishtml, $forumid, ($allowsmilie AND $parsesmilie) ?  1 : 0);
		}
		else
		{
			$result['message'] = '';
		}

		$result['message'] = htmlspecialchars($result['message']);

		if ((defined('VB_API') AND VB_API === true))
		{
			if ($ishtml)
			{
				require_once(DIR . '/includes/class_wysiwygparser.php');
				$html_parser = new vB_WysiwygHtmlParser($vbulletin);
				$result['bbcode'] = $html_parser->parse_wysiwyg_html_to_bbcode($result['message']);
			}
			else
			{
				$result['bbcode'] = $result['message'];
			}
		}
	}
	else
	{
		$result['message'] = $text;
	}
	return $result;
}

// #############################################################################
/**
* Returns the extra buttons as defined by the bbcode editor
*
* @param	string	ID of the editor of which these buttons will be a part
* @param 	boolean	Set to false to disable custom bbcode buttons
*
* @return	string	Extra buttons HTML
*/
function construct_editor_extra_buttons($editorid, $allow_custom_bbcode = true)
{
	global $vbphrase, $vbulletin;

	$extrabuttons = array();

	if ($allow_custom_bbcode and isset($vbulletin->bbcodecache))
	{
		foreach ($vbulletin->bbcodecache AS $bbcode)
		{
			if ($bbcode['buttonimage'] != '')
			{
				$bbcode['tag'] = strtoupper($bbcode['bbcodetag']);
				$extrabuttons[] = $bbcode;
			}
		}
	}

	return $extrabuttons;
}

/*======================================================================*\
|| ####################################################################
|| # 
|| # CVS: $RCSfile$ - $Revision: 62099 $
|| ####################################################################
\*======================================================================*/
?>
