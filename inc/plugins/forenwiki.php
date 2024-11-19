<?php

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

// ACP
$plugins->add_hook("admin_load", "forenwiki_manage_forenwiki");
$plugins->add_hook("admin_config_menu", "forenwiki_admin_config_menu");
$plugins->add_hook("admin_config_permissions", "forenwiki_admin_config_permissions");
$plugins->add_hook("admin_config_action_handler", "forenwiki_admin_config_action_handler");
$plugins->add_hook("admin_formcontainer_end", "forenwiki_usergroup_permission");
$plugins->add_hook("admin_user_groups_edit_commit", "forenwiki_usergroup_permission_commit");

// misc
$plugins->add_hook('misc_start', 'forenwiki_misc');

// global
$plugins->add_hook('global_start', 'forenwiki_global');

// modcp
$plugins->add_hook("modcp_nav", "forenwiki_modcp_nav");
$plugins->add_hook("modcp_start", "forenwiki_modcp");

// Alert
if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
    $plugins->add_hook("global_start", "forenwiki_alerts");
}

//wer ist wo
$plugins->add_hook('fetch_wol_activity_end', 'forenwiki_user_activity');
$plugins->add_hook('build_friendly_wol_location_end', 'forenwiki_location_activity');

function forenwiki_info()
{
    return array(
        "name" => "Forenwikipedia",
        "description" => "Hier kannst du ein Forenwikipedia anlegen, in welchen du alle wichtigen Informationen hinterlegen kannst.",
        "website" => "",
        "author" => "Ales",
        "authorsite" => "https://github.com/Ales12",
        "version" => "2.0",
        "guid" => "",
        "codename" => "",
        "compatibility" => "*"
    );
}

function forenwiki_install()
{
    global $db, $mybb, $cache;

    //Datenbank
    if ($db->engine == 'mysql' || $db->engine == 'mysqli') {
        $db->query("CREATE TABLE `" . TABLE_PREFIX . "wiki_categories` (
          `cid` int(10) NOT NULL auto_increment,
           `sort` int(10) NOT NULL ,
          `category` varchar(500) CHARACTER SET utf8 NOT NULL,
          PRIMARY KEY (`cid`)
        ) ENGINE=MyISAM" . $db->build_create_table_collation());

        $db->query("CREATE TABLE `" . TABLE_PREFIX . "wiki_entries` (
          `wid` int(10) NOT NULL auto_increment,
          `cid` int(11) NOT NULL,
            `sort` int(10) NOT NULL ,
          `linktitle` varchar(255) CHARACTER SET utf8 NOT NULL,
          `link` varchar(255) CHARACTER SET utf8 NOT NULL,
          `wikititle` varchar(255) CHARACTER SET utf8 NOT NULL,
            `wikitext` longtext CHARACTER SET utf8 NOT NULL,
                 `uid` int(10) NOT NULL,
                 `accepted` int(10) DEFAULT '0' NOT NULL,
          PRIMARY KEY (`wid`)
        ) ENGINE=MyISAM" . $db->build_create_table_collation());
    }

    // Spalte bei Usertabelle hinzufügen
    $db->add_column("usergroups", "canaddwikipage", "tinyint NOT NULL default '0'");
    $cache->update_usergroups();

    // Einstellungen
    $setting_group = array(
        'name' => 'wiki',
        'title' => 'Einstellungen für das Forenwikipedia',
        'description' => 'Hier kannst du alle wichtigen Einstellungen für dein Wiki machen.',
        'disporder' => 5, // The order your setting group will display
        'isdefault' => 0
    );

    $gid = $db->insert_query("settinggroups", $setting_group);

    $setting_array = array(
        'wiki_welcometext' => array(
            'title' => 'Willkommentext',
            'description' => 'Hier kannst du die Informationen einfügen, was auf der Startseite stehen soll. BBCodes und HTML sind aktiv.',
            'optionscode' => 'textarea',
            'value' => 'Willkommen auf dem Forenwikipedia. Hier kannst du nun einen kleinen Informationstext einfügen. Kurze Einleitung oder irgendwas anderes. Sei einfach Kreativ.', // Default
            'disporder' => 1
        ),
    );

    foreach ($setting_array as $name => $setting) {
        $setting['name'] = $name;
        $setting['gid'] = $gid;

        $db->insert_query('settings', $setting);
    }

    // templates

    $insert_array = array(
        'title' => 'forenwiki',
        'template' => $db->escape_string('<html>
<head>
<title>{$lang->forenwiki}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0"><tr>
	{$wiki_nav}

<td class="trow2" valign="top">
	<div class="wiki_title">{$lang->forenwiki}</div>
	<div class="wiki_textbox">{$forenwiki_desc}</div>
	
	</td>
</tr>
</table>
{$footer}
</body>
</html>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title' => 'forenwiki_add_entry',
        'template' => $db->escape_string('<html>
<head>
<title>{$lang->forenwiki_add_entry}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0"><tr>
	{$wiki_nav}

<td class="trow2" valign="top">
	<div class="wiki_title">{$lang->forenwiki_add_entry}</div>
	{$newentry_alert}
	<form action="misc.php?action=add_entry" method="post" id="submitentry">	
		<table width="80%" style="margin: auto;" cellpadding="5">
			<tr>
				<td class="trow1"><strong>{$lang->forenwiki_wikititle}</strong></td>
				<td class="trow2">	<input type="text" class="textbox" name="wikititle" id="wikititle" size="40" maxlength="1155" placeholder="Title des Wikieintrags"></td>
			</tr>
						<tr>
				<td class="trow1"><strong>{$lang->forenwiki_wikicat}</strong></td>
							<td class="trow2"><select name="cid">{$catoption}</select></td>
			</tr>
					<tr>
				<td class="trow1"><strong>{$lang->forenwiki_wikisort}</strong>
					<div class="smalltext">{$lang->forenwiki_wikisort_desc}</div>
						</td>
				<td class="trow2">	<input type="number" class="textbox" name="sort" id="sort" size="40" maxlength="1155" placeholder="0"></td>
			</tr>
								<tr>
				<td class="trow1"><strong>{$lang->forenwiki_wikilink_title}</strong>
					<div class="smalltext">{$lang->forenwiki_wikilink_title_desc}</div>
						</td>
				<td class="trow2">	<input type="text" class="textbox" name="linktitle" id="linktitle" size="40" maxlength="1155" placeholder="Unsere Forenregeln"></td>
			</tr>
											<tr>
				<td class="trow1"><strong>{$lang->forenwiki_wikilink}</strong>
					<div class="smalltext">{$lang->forenwiki_wikilink_desc}</div>
						</td>
				<td class="trow2">	<input type="text" class="textbox" name="link" id="link" size="40" maxlength="1155" placeholder="forenregeln"></td>
			</tr>
			<tr><td class="trow1" colspan="2" align="center"><strong>{$lang->forenwiki_wikitext}</strong>
				<div class="smalltext">{$lang->forenwiki_wikitext_desc}</div>
				</td>
			</tr>
			<tr>
				<td class="trow2" colspan="2">
					<textarea class="textarea" name="wikitext" id="wikitext" rows="20" cols="55" style="width: 100%; margin: auto;"></textarea>
			</td>
			</tr>
						<tr><td class="trow1" colspan="2" align="center">
							<input type="submit" name="submitentry" id="submitentry" value="{$lang->forenwiki_wiki_submit}" class="buttom">
				</td>
			</tr>
		</table>
	</form>
	
	</td>
</tr>
</table>
{$footer}
</body>
</html>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title' => 'forenwiki_editentries',
        'template' => $db->escape_string('<html>
<head>
<title>{$lang->forenwiki_edit_entry}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0"><tr>
	{$wiki_nav}

<td class="trow2" valign="top">
	<div class="wiki_title">{$lang->forenwiki_edit_entry}</div>
	{$newentry_alert}
	<form action="misc.php?action=edit_entry" method="post" id="editentry">	
		<input type="hidden" class="textbox" name="wid" id="wid" size="40" maxlength="1155" value="{$wid}">
		<table width="80%" style="margin: auto;" cellpadding="5">
			<tr>
				<td class="trow1"><strong>{$lang->forenwiki_wikititle}</strong></td>
				<td class="trow2">	<input type="text" class="textbox" name="wikititle" id="wikititle" size="40" maxlength="1155" value="{$wikititle}"></td>
			</tr>
						<tr>
				<td class="trow1"><strong>{$lang->forenwiki_wikicat}</strong></td>
							<td class="trow2"><select name="cid">{$catoption}</select></td>
			</tr>
					<tr>
				<td class="trow1"><strong>{$lang->forenwiki_wikisort}</strong>
					<div class="smalltext">{$lang->forenwiki_wikisort_desc}</div>
						</td>
				<td class="trow2">	<input type="number" class="textbox" name="sort" id="sort" size="40" maxlength="1155"value="{$sort}"></td>
			</tr>
								<tr>
				<td class="trow1"><strong>{$lang->forenwiki_wikilink_title}</strong>
					<div class="smalltext">{$lang->forenwiki_wikilink_title_desc}</div>
						</td>
				<td class="trow2">	<input type="text" class="textbox" name="linktitle" id="linktitle" size="40" maxlength="1155" value="{$linktitle}"></td>
			</tr>
											<tr>
				<td class="trow1"><strong>{$lang->forenwiki_wikilink}</strong>
					<div class="smalltext">{$lang->forenwiki_wikilink_desc}</div>
						</td>
				<td class="trow2">	<input type="text" class="textbox" name="link" id="link" size="40" maxlength="1155" value="{$link}"></td>
			</tr>
			<tr><td class="trow1" colspan="2" align="center"><strong>{$lang->forenwiki_wikitext}</strong>
				<div class="smalltext">{$lang->forenwiki_wikitext_desc}</div>
				</td>
			</tr>
			<tr>
				<td class="trow2" colspan="2">
					<textarea class="textarea" name="wikitext" id="wikitext" rows="20" cols="55" style="width: 100%; margin: auto;">{$wikitext}</textarea>
			</td>
			</tr>
						<tr><td class="trow1" colspan="2" align="center">
							<input type="submit" name="editentry" id="editentry" value="{$lang->forenwiki_wiki_submit}" class="buttom">
				</td>
			</tr>
		</table>
	</form>
	
	</td>
</tr>
</table>
{$footer}
</body>
</html>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title' => 'forenwiki_entry',
        'template' => $db->escape_string('<html>
<head>
<title>{$wikititle}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0"><tr>
	{$wiki_nav}

<td class="trow2" valign="top">
	<div class="wiki_title">{$wikititle}</div>
	<div class="wiki_textbox">{$wikitext}</div>
	
	</td>
</tr>
</table>
{$footer}
</body>
</html>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title' => 'forenwiki_menu',
        'template' => $db->escape_string('<li><a href="{$mybb->settings[\'bburl\']}/misc.php?action=forenwiki" >{$lang->forenwiki_global}</a></li>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title' => 'forenwiki_modcp',
        'template' => $db->escape_string('<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->forenwiki_modcp}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
{$modcp_nav}
<td valign="top">
	<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" align="center" colspan="4"><strong>{$lang->forenwiki_modcp}</strong></td>
</tr>
<tr>
<td class="trow1">
{$modcp_newentry}
	</td>
</tr>
</table>
	</td>
	</tr>
	</table>
{$footer}
</body>
</html>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title' => 'forenwiki_modcp_entry',
        'template' => $db->escape_string('<table cellspacing="{$theme[\'borderwidth\']}" cellpadding="5" width="90%" style="margin: 20px auto;">
	<tr>
		<td class="trow1" width="30%"><strong>{$lang->forenwiki_modcp_title}</strong></td>
		<td class="trow2">{$wikititle}</td>
	</tr>
		<tr>
		<td class="trow1" width="30%"><strong>{$lang->forenwiki_modcp_author}</strong></td>
		<td class="trow2">{$wikiauthor}</td>
	</tr>
			<tr>
		<td class="trow1" width="30%"><strong>{$lang->forenwiki_modcp_cat}</strong></td>
		<td class="trow2">{$wikicat}</td>
	</tr>
			<tr>
		<td class="trow1" width="30%"><strong>{$lang->forenwiki_modcp_entry}</strong></td>
				<td class="trow2"><div class="wiki_modcp_entry">{$wikitext}</div></td>
	</tr>
				<tr>
		<td class="trow1" colspan="2" align="center"><strong>{$wikioption}</strong></td>
	</tr>
	</table>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title' => 'forenwiki_modcp_nav',
        'template' => $db->escape_string('<tr>
			<td class="trow1 smalltext">
			<a href="modcp.php?action=forenwiki_control"  class="modcp_nav_item modcp_nav_modqueue">{$lang->forenwiki_modcp_nav}</a>
			</td>
</tr>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title' => 'forenwiki_nav',
        'template' => $db->escape_string('<<td class="trow1" width="20%" valign="top">
	<div class="wiki_navi">
		<div class="wiki_nav_cat thead">{$lang->forenwiki_nav}</div>
		<div class="wiki_entry"><a href="misc.php?action=forenwiki">{$lang->forenwiki_main}</a></div>
		{$wiki_addentry}
		{$wiki_cat}
</div>
	</td>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title' => 'forenwiki_nav_add',
        'template' => $db->escape_string('<div class="wiki_entry"><a href="misc.php?action=add_entry">{$lang->forenwiki_add_entry}</a></div>
<div class="wiki_entry"><a href="misc.php?action=own_wikientries">{$lang->forenwiki_ownentries}</a></div>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title' => 'forenwiki_nav_cat',
        'template' => $db->escape_string('<div class="wiki_nav_cat tcat">{$category}</div>
{$wiki_entry}'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title' => 'forenwiki_nav_entry',
        'template' => $db->escape_string('<div class="wiki_entry"><a href="misc.php?wikientry={$link}">{$linktitle}</a></div>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title' => 'forenwiki_newentry_accept',
        'template' => $db->escape_string('<div class="red_alert" style="margin: 20px; text-align: center;">{$lang->forenwiki_wiki_sumbitinfo}</div>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title' => 'forenwiki_newentry_alert',
        'template' => $db->escape_string('<div class="red_alert"><a href="modcp.php?action=forenwiki_control">{$alert_newentry}</a></div>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title' => 'forenwiki_ownentries',
        'template' => $db->escape_string('<html>
<head>
<title>{$lang->forenwiki_ownentries}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0"><tr>
	{$wiki_nav}
<td class="trow2" valign="top">
	<div class="wiki_title">{$lang->forenwiki_ownentries}</div>
		{$statusentry}
</td>
</tr>
</table>
{$footer}
</body>
</html>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title' => 'forenwiki_ownentries_entries',
        'template' => $db->escape_string('<tr>
	<td class="trow1">
		<strong>	{$wikititle}</strong>
	</td>
		<td class="trow2">
		<strong>	{$wikicat}</strong>
	</td>
		<td class="trow1">
{$wikilink}
	</td>
		<td class="trow2">
{$wikioption}
	</td>
</tr>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title' => 'forenwiki_ownentries_status',
        'template' => $db->escape_string('<table width="90%" style="margin: 20px auto;" cellpadding="5">
<tr>
	<td class="thead" colspan="4"><strong>{$entrystatus}</strong></td>
</tr>
<tr>
	<td class="tcat" width="30%"><strong>{$lang->forenwiki_owntitle}</strong></td>
		<td class="tcat" width="30%"><strong>{$lang->forenwiki_owncat}</strong></td>
		<td class="tcat" width="30%"><strong>{$lang->forenwiki_ownlink}</strong></td>
		<td class="tcat" width="10%"><strong>{$lang->forenwiki_ownoption}</strong></td>
</tr>
{$ownentry}
</table>'),
        'sid' => '-1',
        'version' => '',
        'dateline' => TIME_NOW
    );
    $db->insert_query("templates", $insert_array);


    //CSS einfügen
    $css = array(
        'name' => 'forenwiki.css',
        'tid' => 1,
        'attachedto' => '',
        "stylesheet" => '.wiki_navi{
		display: flex;
		flex-direction: column;
		justify-content: center;
}

.wiki_nav_cat{
	text-align: center;
	font-weight: bold;
}

.wiki_entry{
	margin: 5px;	
}

.wiki_entry::before{
		content: "» ";
	padding-right: 2px;	
}

/*Main*/
.wiki_title{
	font-size: 20px;
	text-align: center;
	font-weight: bold;
	margin: 20px 10px 15px 10px;
}

.wiki_textbox{
	margin: 10px 20px;	
}

/*modcp*/
.wiki_modcp_entry{
	padding: 5px;
	text-align: justify;
	max-height: 500px;
	overflow: auto;
}
        ',
        'cachefile' => $db->escape_string(str_replace('/', '', 'forenwiki.css')),
        'lastmodified' => time()
    );

    require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

    $sid = $db->insert_query("themestylesheets", $css);
    $db->update_query("themestylesheets", array("cachefile" => "css.php?stylesheet=" . $sid), "sid = '" . $sid . "'", 1);

    $tids = $db->simple_select("themes", "tid");
    while ($theme = $db->fetch_array($tids)) {
        update_theme_stylesheet_list($theme['tid']);
    }

    // Don't forget this!
    rebuild_settings();

}

function forenwiki_is_installed()
{
    global $db;
    if ($db->table_exists("wiki_entries")) {
        return true;
    }
    return false;
}
function forenwiki_uninstall()
{
    global $db, $cache;

    // Datenbank wieder löschen
    if ($db->table_exists("wiki_categories")) {
        $db->drop_table("wiki_categories");
    }

    if ($db->table_exists("wiki_entries")) {
        $db->drop_table("wiki_entries");
    }
    // Spalte wieder aus Usertabelle entfernen
    if ($db->field_exists("canaddwikipage", "usergroups")) {
        $db->drop_column("usergroups", "canaddwikipage");
    }

    // Einstellungen wieder löschen
    $db->delete_query('settings', "name IN ('wiki_welcometext')");
    $db->delete_query('settinggroups', "name = 'wiki'");

    $db->delete_query("templates", "title LIKE '%forenwiki%'");
    // Don't forget this
    rebuild_settings();

    require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";
    $db->delete_query("themestylesheets", "name = 'forenwiki.css'");
    $query = $db->simple_select("themes", "tid");
    while ($theme = $db->fetch_array($query)) {
        update_theme_stylesheet_list($theme['tid']);
        rebuild_settings();
    }


}

function forenwiki_activate()
{
    global $db, $cache;
    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

        if (!$alertTypeManager) {
            $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
        }

        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
        $alertType->setCode('forenwiki_acceptentry'); // The codename for your alert type. Can be any unique string.
        $alertType->setEnabled(true);
        $alertType->setCanBeUserDisabled(true);

        $alertTypeManager->add($alertType);

        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
        $alertType->setCode('forenwiki_denyentry'); // The codename for your alert type. Can be any unique string.
        $alertType->setEnabled(true);
        $alertType->setCanBeUserDisabled(true);

        $alertTypeManager->add($alertType);

    }

    require MYBB_ROOT . "/inc/adminfunctions_templates.php";
    find_replace_templatesets("header", "#" . preg_quote('{$menu_calendar}') . "#i", '{$menu_calendar} {$menu_forenwiki} ');
    find_replace_templatesets("header", "#" . preg_quote('<navigation>') . "#i", '{$global_newentry_alert} <navigation>');
    find_replace_templatesets("modcp_nav_forums_posts", "#" . preg_quote('{$nav_modlogs}') . "#i", '{$nav_modlogs}{$nav_forenwiki}');

}

function forenwiki_deactivate()
{
    global $db, $cache;
    //Alertseinstellungen
    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

        if (!$alertTypeManager) {
            $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
        }

        $alertTypeManager->deleteByCode('forenwiki_acceptentry');
        $alertTypeManager->deleteByCode('forenwiki_denyentry');
    }

    require MYBB_ROOT . "/inc/adminfunctions_templates.php";
    find_replace_templatesets("header", "#" . preg_quote('{$menu_forenwiki}') . "#i", '', 0);
    find_replace_templatesets("header", "#" . preg_quote('{$global_newentry_alert}') . "#i", '', 0);
    find_replace_templatesets("modcp_nav_forums_posts", "#" . preg_quote('{$nav_forenwiki}') . "#i", '', 0);

}


// Usergruppen-Berechtigungen
function forenwiki_usergroup_permission()
{
    global $mybb, $lang, $form, $form_container, $run_module;

    if ($run_module == 'user' && !empty($form_container->_title) & !empty($lang->misc) & $form_container->_title == $lang->misc) {
        $forenwiki_options = array(
            $form->generate_check_box('canaddwikipage', 1, "Kann einen neuen Wikieintrag hinzufügen?", array("checked" => $mybb->input['canaddwikipage'])),
        );
        $form_container->output_row("Einstellung für Forenwikipedia", "", "<div class=\"group_settings_bit\">" . implode("</div><div class=\"group_settings_bit\">", $forenwiki_options) . "</div>");
    }
}

function forenwiki_usergroup_permission_commit()
{
    global $db, $mybb, $updated_group;
    $updated_group['canaddwikipage'] = $mybb->get_input('canaddwikipage', MyBB::INPUT_INT);
}

// Admin CP konfigurieren - 
//Action Handler erstellen


function forenwiki_admin_config_action_handler(&$actions)
{
    $actions['forenwiki'] = array('active' => 'forenwiki', 'file' => 'forenwiki');
}

//ACP Permissions - Berechtigungen für die Admins (über ACP einstellbar)

function forenwiki_admin_config_permissions(&$admin_permissions)
{
    global $lang;
    $lang->load('forenwiki');
    $admin_permissions['forenwiki'] = $lang->forenwiki_canadmin;
    return $admin_permissions;
}


//ACP Menüpunkt unter Konfigurationen erstellen

function forenwiki_admin_config_menu(&$sub_menu)
{
    $sub_menu[] = [
        "id" => "forenwiki",
        "title" => "Forenwikipedia verwalten",
        "link" => "index.php?module=config-forenwiki"
    ];
}


function forenwiki_manage_forenwiki()
{
    global $mybb, $db, $lang, $page, $run_module, $action_file;
    $lang->load('forenwiki');
    if ($page->active_action != 'forenwiki') {
        return false;
    }

    if ($run_module == 'config' && $action_file == "forenwiki") {

        //Aufnahmestop Übersicht 
        if ($mybb->input['action'] == "" || !isset($mybb->input['action'])) {
            // Add a breadcrumb - Navigation Seite 
            $page->add_breadcrumb_item($lang->forenwiki_manage);

            //Header Auswahl Felder im Aufnahmestop verwalten Menü hinzufügen
            $page->output_header($lang->forenwiki_manage . " - " . $lang->forenwiki_overview);

            //Übersichtsseite über alle Stops
            $sub_tabs['forenwiki'] = [
                "title" => $lang->forenwiki_overview_entries,
                "link" => "index.php?module=config-forenwiki",
                "description" => $lang->forenwiki_overview_entries_desc
            ];

            //Neuen Kategorie hinzufügen
            $sub_tabs['forenwiki_cat_add'] = [
                "title" => $lang->forenwiki_add_cat,
                "link" => "index.php?module=config-forenwiki&amp;action=add_cat",
                "description" => $lang->forenwiki_add_cat_desc
            ];

            // neuen Eintrag hinzufügen
            $sub_tabs['forenwiki_entry_add'] = [
                "title" => $lang->forenwiki_add_entry,
                "link" => "index.php?module=config-forenwiki&amp;action=add_entry",
                "description" => $lang->forenwiki_add_entry_desc
            ];
            $page->output_nav_tabs($sub_tabs, 'forenwiki');

            // Zeige Fehler an
            if (isset($errors)) {
                $page->output_inline_error($errors);
            }

            //Übersichtsseite erstellen 
            $form = new Form("index.php?module=config-forenwiki", "post");


            //Die Überschriften!
            $form_container = new FormContainer("<div style=\"text-align: center;\">$lang->forenwiki_manage</div>");
            $form_container->output_row_header("<div style=\"text-align: center;\">$lang->forenwiki_title</div>");
            $form_container->output_row_header("<div style=\"text-align: center;\">$lang->forenwiki_link</div>");
            $form_container->output_row_header("<div style=\"text-align: center;\">$lang->forenwiki_status</div>");
            //Optionen
            $form_container->output_row_header($lang->forenwiki_option, array('style' => 'text-align: center; width: 10%;'));

            // Gib mir bitte alle Kategorien
            $get_all_cat = $db->simple_select("wiki_categories", "*", "", ["order_by" => 'sort', 'order_dir' => 'ASC']);

            while ($all_cat = $db->fetch_array($get_all_cat)) {
                $form_container->output_cell('<div style="text-align: center; width: 100%;"><STRONG>' . htmlspecialchars_uni($all_cat['category']) . '</strong></div>', array("colspan" => "3", "style" => "padding:10px;"));
                $popup = new PopupMenu("forenwiki_cat_{$all_cat['cid']}", $lang->forenwiki_options);
                $popup->add_item(
                    $lang->forenwiki_cat_edit,
                    "index.php?module=config-forenwiki&amp;action=edit_cat&amp;cid={$all_cat['cid']}"
                );
                $popup->add_item(
                    $lang->forenwiki_cat_delete,
                    "index.php?module=config-forenwiki&amp;action=delete_cat&amp;cid={$all_cat['cid']}"
                    . "&amp;my_post_key={$mybb->post_code}"
                );
                $form_container->output_cell($popup->fetch(), array("class" => "align_center"));
                $form_container->construct_row();

                $cid = $all_cat['cid'];
                $get_all_entries = $db->simple_select("wiki_entries", "*", "cid = {$cid}", ["order_by" => 'sort', 'order_dir' => 'ASC']);
                while ($all_entries = $db->fetch_array($get_all_entries)) {
                    $form_container->output_cell('<div style="padding-left: 20px;"><strong>' . htmlspecialchars_uni($all_entries['wikititle']) . '</strong></div>', array('style' => 'width: 40%;'));
                    $form_container->output_cell('<div style="padding-left: 20px;"><strong>Zum Wikieintrag:</strong> <a href="' . htmlspecialchars_uni($mybb->settings['bburl']) . '/misc.php?wikientry=' . htmlspecialchars_uni($all_entries['link']) . '" target="_blank">' . htmlspecialchars_uni($all_entries['linktitle']) . '</a></div>', array('style' => 'width: 40%;'));
                    //Pop Up für Bearbeiten & Löschen
                    $popup = new PopupMenu("forenwiki_entry_{$all_entries['wid']}", $lang->forenwiki_options);
                    $popup->add_item(
                        $lang->forenwiki_edit,
                        "index.php?module=config-forenwiki&amp;action=edit_entry&amp;wid={$all_entries['wid']}"
                    );
                    $popup->add_item(
                        $lang->forenwiki_delete,
                        "index.php?module=config-forenwiki&amp;action=delete_entry&amp;wid={$all_entries['wid']}"
                        . "&amp;my_post_key={$mybb->post_code}"
                    );
                    if ($all_entries['accepted'] == "0") {
                        $entry_status = "<img src='styles/default/images/icons/no_change.png' title='{$lang->forenwiki_noaccept}'>";
                    } elseif ($all_entries['accepted'] == "-1") {
                        $entry_status = "<img src='styles/default/images/icons/error.png' title='{$lang->forenwiki_deny}'>";
                    } else {
                        $entry_status = "<img src='styles/default/images/icons/success.png' title='{$lang->forenwiki_accept}'>";
                    }

                    $form_container->output_cell('<div style="text-align: center;">' . $entry_status . '</div>', array('style' => 'width: 10%;'));

                    $form_container->output_cell($popup->fetch(), array("class" => "align_center"));
                    $form_container->construct_row();
                }


            }
            $form_container->end();
            $form->end();
            $page->output_footer();

            exit;
        }

        if ($mybb->input['action'] == "add_cat") {
            // Eintragen
            if ($mybb->request_method == "post") {
                // Prüfe, ob alle erforderlichen Felder ausgefüllt wurden
                if (empty($mybb->input['category'])) {
                    $error[] = $lang->forenwiki_error_category;
                }
                if (empty($mybb->input['catsort'])) {
                    $error[] = $lang->forenwiki_error_sort;
                }

                if (empty($error)) {
                    $category = $db->escape_string($mybb->input['category']);
                    $catsort = (int) $mybb->input['catsort'];

                    $new_cat = array(
                        "category" => $category,
                        "sort" => $catsort
                    );

                    $db->insert_query("wiki_categories", $new_cat);

                    $mybb->input['module'] = "forenwiki";
                    $mybb->input['action'] = $lang->forenwiki_cat_solved;
                    log_admin_action(htmlspecialchars_uni($mybb->input['category']));

                    flash_message($lang->forenwiki_cat_solved, 'success');
                    admin_redirect("index.php?module=config-forenwiki");

                }

            }

            $page->add_breadcrumb_item($lang->forenwiki_cat);

            // Build options header
            $page->output_header($lang->forenwiki_manage . " - " . $lang->forenwiki_overview);

            //Übersichtsseite über alle Stops
            $sub_tabs['forenwiki'] = [
                "title" => $lang->forenwiki_overview_entries,
                "link" => "index.php?module=config-forenwiki",
                "description" => $lang->forenwiki_overview_entries_desc
            ];
            //Neuen Kategorie hinzufügen
            $sub_tabs['forenwiki_cat_add'] = [
                "title" => $lang->forenwiki_add_cat,
                "link" => "index.php?module=config-forenwiki&amp;action=add_cat",
                "description" => $lang->forenwiki_add_cat_desc
            ];

            // neuen Eintrag hinzufügen
            $sub_tabs['forenwiki_entry_add'] = [
                "title" => $lang->forenwiki_add_entry,
                "link" => "index.php?module=config-forenwiki&amp;action=add_entry",
                "description" => $lang->forenwiki_add_entry_desc
            ];
            $page->output_nav_tabs($sub_tabs, 'forenwiki_cat_add');


            // Erstellen der "Formulareinträge"
            $form = new Form("index.php?module=config-forenwiki&amp;action=add_cat", "post", "", 1);
            $form_container = new FormContainer($lang->forenwiki_cat);

            $form_container->output_row(
                $lang->forenwiki_addcat . "<em>*</em>",
                $lang->forenwiki_addcat_desc,
                $form->generate_text_box('category', isset($mybb->input['category']))
            );

            $form_container->output_row(
                $lang->forenwiki_addsort . "<em>*</em>", //Aktueller Stop?
                $lang->forenwiki_addsort_desc,
                $form->generate_numeric_field('catsort', isset($mybb->input['catsort']))
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->forenwiki_send);
            $form->output_submit_wrapper($buttons);
            $form->end();
            $page->output_footer();

            exit;
        }

        // Kategorie ändern
        if ($mybb->input['action'] == "edit_cat") {
            // Eintragen
            if ($mybb->request_method == "post") {
                // Prüfe, ob alle erforderlichen Felder ausgefüllt wurden
                if (empty($mybb->input['category'])) {
                    $error[] = $lang->forenwiki_error_category;
                }
                if (empty($mybb->input['catsort'])) {
                    $error[] = $lang->forenwiki_error_sort;
                }

                if (empty($error)) {
                    $category = $db->escape_string($mybb->input['category']);
                    $catsort = (int) $mybb->input['catsort'];
                    $cid = $mybb->get_input('cid', MyBB::INPUT_INT);

                    $edit_cat = array(
                        "category" => $category,
                        "sort" => $catsort
                    );

                    $db->update_query("wiki_categories", $edit_cat, "cid = {$cid}");

                    $mybb->input['module'] = "forenwiki";
                    $mybb->input['action'] = $lang->forenwiki_catedit_solved;
                    log_admin_action(htmlspecialchars_uni($mybb->input['category']));

                    flash_message($lang->forenwiki_catedit_solved, 'success');
                    admin_redirect("index.php?module=config-forenwiki");

                }

            }

            $page->add_breadcrumb_item($lang->forenwiki_cat);

            // Build options header
            $page->output_header($lang->forenwiki_manage . " - " . $lang->forenwiki_overview);

            //Übersichtsseite über alle Stops
            $sub_tabs['forenwiki'] = [
                "title" => $lang->forenwiki_overview_entries,
                "link" => "index.php?module=config-forenwiki",
                "description" => $lang->forenwiki_overview_entries_desc
            ];
            //Neuen Kategorie hinzufügen
            $sub_tabs['forenwiki_cat_add'] = [
                "title" => $lang->forenwiki_edit_cat,
                "link" => "index.php?module=config-forenwiki&amp;action=edit_cat",
                "description" => $lang->forenwiki_edit_cat_desc
            ];

            $page->output_nav_tabs($sub_tabs, 'forenwiki_cat_add');

            // Get the data
            $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
            $query = $db->simple_select("wiki_categories", "*", "cid={$cid}");
            $edit_cat = $db->fetch_array($query);

            // Erstellen der "Formulareinträge"
            $form = new Form("index.php?module=config-forenwiki&amp;action=edit_cat", "post", "", 1);
            echo $form->generate_hidden_field('cid', $cid);
            $form_container = new FormContainer($lang->forenwiki_cat);

            $form_container->output_row(
                $lang->forenwiki_addcat . "<em>*</em>",
                $lang->forenwiki_addcat_desc,
                $form->generate_text_box('category', $edit_cat['category'])
            );

            $form_container->output_row(
                $lang->forenwiki_addsort . "<em>*</em>", //Aktueller Stop?
                $lang->forenwiki_addsort_desc,
                $form->generate_numeric_field('catsort', $edit_cat['sort'])
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->forenwiki_send);
            $form->output_submit_wrapper($buttons);
            $form->end();
            $page->output_footer();

            exit;
        }

        // Kategorie löschen

        if ($mybb->input['action'] == "delete_cat") {
            // Get the data
            $cid = $mybb->get_input('cid', MyBB::INPUT_INT);
            $query = $db->simple_select("wiki_categories", "*", "cid={$cid}");
            $delete_cat = $db->fetch_array($query);

            if (empty($cid)) {
                flash_message($lang->forenwiki_error_option, 'error');
                admin_redirect("index.php?module=config-forenwiki");

            }
            // Cancel button pressed?
            if (isset($mybb->input['no']) && $mybb->input['no']) {
                admin_redirect("index.php?module=config-forenwiki");
            }

            if (!verify_post_check($mybb->input['my_post_key'])) {
                flash_message($lang->invalid_post_verify_key2, 'error');
                admin_redirect("index.php?module=config-forenwiki");
            } else {
                if ($mybb->request_method == "post") {

                    $db->delete_query("wiki_categories", "cid='{$cid}'");
                    $db->delete_query("wiki_entries", "cid='{$cid}'");
                    $mybb->input['module'] = "forenwiki";
                    $mybb->input['action'] = $lang->forenwiki_delete_cat_solved;
                    log_admin_action(htmlspecialchars_uni($delete_cat['stoptitel']));

                    flash_message($lang->forenwiki_delete_cat_solved, 'success');
                    admin_redirect("index.php?module=config-forenwiki");
                } else {

                    $page->output_confirm_action(
                        "index.php?module=config-forenwiki&amp;action=delete_cat&amp;cid={$cid}",
                        $lang->forenwiki_delete_entry_question
                    );
                }
            }
            exit;
        }

        // Wiki eintrag hinzufügen
        if ($mybb->input['action'] == "add_entry") {
            // Eintragen
            if ($mybb->request_method == "post") {
                // Prüfe, ob alle erforderlichen Felder ausgefüllt wurden
                if (empty($mybb->input['wikilinktitle'])) {
                    $error[] = $lang->forenwiki_error_linktitle;
                }
                if (empty($mybb->input['wikisort'])) {
                    $error[] = $lang->forenwiki_error_sort;
                }
                if (empty($mybb->input['wikilink'])) {
                    $error[] = $lang->forenwiki_error_link;
                }
                if (empty($mybb->input['wikititle'])) {
                    $error[] = $lang->forenwiki_error_wikititle;
                }
                if (empty($mybb->input['wikientry'])) {
                    $error[] = $lang->forenwiki_error_wikitext;
                }


                if (empty($errors)) {
                    $wikisort = (int) $mybb->input['wikisort'];
                    $wikicat = (int) $mybb->input['wikicat'];
                    $wikilinktitle = $db->escape_string($mybb->input['wikilinktitle']);
                    $wikilink = $db->escape_string($mybb->input['wikilink']);
                    $wikititle = $db->escape_string($mybb->input['wikititle']);
                    $wikitext = $db->escape_string($mybb->input['wikitext']);
                    $uid = (int) $mybb->user['uid'];

                    $new_entry = array(
                        "cid" => $wikicat,
                        "sort" => $wikisort,
                        "linktitle" => $wikilinktitle,
                        "link" => $wikilink,
                        "wikititle" => $wikititle,
                        "wikitext" => $wikitext,
                        "uid" => $uid,
                        "accepted" => 1
                    );

                    $db->insert_query("wiki_entries", $new_entry);

                    $mybb->input['module'] = "forenwiki";
                    $mybb->input['action'] = $lang->forenwiki_entry_solved;
                    log_admin_action(htmlspecialchars_uni($mybb->input['wikitext']));

                    flash_message($lang->forenwiki_entry_solved, 'success');
                    admin_redirect("index.php?module=config-forenwiki");

                }

            }

            $page->add_breadcrumb_item($lang->forenwiki_add_entry);
            $page->extra_header .= <<<EOF
                
            <link rel="stylesheet" href="../jscripts/sceditor/themes/mybb.css" type="text/css" media="all" />
            <script type="text/javascript" src="../jscripts/sceditor/jquery.sceditor.bbcode.min.js?ver=1832"></script>
            <script type="text/javascript" src="../jscripts/bbcodes_sceditor.js?ver=1832"></script>
            <script type="text/javascript" src="../jscripts/sceditor/plugins/undo.js?ver=1832"></script> 
            EOF;


            // Build options header
            $page->output_header($lang->forenwiki_manage . " - " . $lang->forenwiki_overview);

            //Übersichtsseite über alle Stops
            $sub_tabs['forenwiki'] = [
                "title" => $lang->forenwiki_overview_entries,
                "link" => "index.php?module=config-forenwiki",
                "description" => $lang->forenwiki_overview_entries_desc
            ];
            //Neuen Kategorie hinzufügen
            $sub_tabs['forenwiki_cat_add'] = [
                "title" => $lang->forenwiki_add_cat,
                "link" => "index.php?module=config-forenwiki&amp;action=add_cat",
                "description" => $lang->forenwiki_add_cat_desc
            ];

            // neuen Eintrag hinzufügen
            $sub_tabs['forenwiki_entry_add'] = [
                "title" => $lang->forenwiki_add_entry,
                "link" => "index.php?module=config-forenwiki&amp;action=add_entry",
                "description" => $lang->forenwiki_add_entry_desc
            ];
            $page->output_nav_tabs($sub_tabs, 'forenwiki_entry_add');


            // Erstellen der "Formulareinträge"
            $form = new Form("index.php?module=config-forenwiki&amp;action=add_entry", "post", "", 1);
            $form_container = new FormContainer($lang->forenwiki_add_entry);

            $form_container->output_row(
                $lang->forenwiki_wikititle . " <em>*</em>",
                $form->generate_text_box('wikititle', isset($mybb->input['wikititle']))
            );
            $query = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "wiki_categories`");
            $options = array();
            //Wenn es welche gibt: 
            if (mysqli_num_rows($query) > 0) {
                while ($output = $db->fetch_array($query)) {
                    $options[$output['cid']] = $output['category'];
                }
                $form_container->output_row($lang->forenwiki_wikicat . " <em>*</em>", '', $form->generate_select_box('wikicat', $options, array($mybb->get_input('wikicat', MyBB::INPUT_INT)), array('id' => 'wikicat')), 'wikicat');
            }
            $form_container->output_row(
                $lang->forenwiki_wikisort . " <em>*</em>",
                $lang->forenwiki_wikisort_desc,
                $form->generate_numeric_field('wikisort', $mybb->get_input('wikisort'))
            );

            $form_container->output_row(
                $lang->forenwiki_wikilink_title . " <em>*</em>",
                $lang->forenwiki_wikilink_title_desc,
                $form->generate_text_box('wikilinktitle', isset($mybb->input['wikilinktitle']))
            );

            $form_container->output_row(
                $lang->forenwiki_wikilink . " <em>*</em>",
                $lang->forenwiki_wikilink_desc,
                $form->generate_text_box('wikilink', isset($mybb->input['wikilink']))
            );

            $text_editor = $form->generate_text_area(
                'wikitext',
                isset($mybb->input['wikitext']),
                array(
                    'id' => 'wikitext',
                    'rows' => '25',
                    'cols' => '70',
                    'style' => 'height: 400px; width: 75%'
                )
            );
            $text_editor .= build_mycode_inserter('wikitext');
            $form_container->output_row(
                $lang->forenwiki_wikitext . " <em>*</em>",
                $lang->forenwiki_wikitext_desc,
                $text_editor,
                'text'
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->forenwiki_wiki_submit);
            $form->output_submit_wrapper($buttons);
            $form->end();
            $page->output_footer();

            exit;
        }


        if ($mybb->input['action'] == "edit_entry") {
            // Eintragen
            if ($mybb->request_method == "post") {
                // Prüfe, ob alle erforderlichen Felder ausgefüllt wurden
                if (empty($mybb->input['wikilinktitle'])) {
                    $error[] = $lang->forenwiki_error_linktitle;
                }
                if (empty($mybb->input['wikisort'])) {
                    $error[] = $lang->forenwiki_error_sort;
                }
                if (empty($mybb->input['wikilink'])) {
                    $error[] = $lang->forenwiki_error_link;
                }
                if (empty($mybb->input['wikititle'])) {
                    $error[] = $lang->forenwiki_error_wikititle;
                }
                if (empty($mybb->input['wikientry'])) {
                    $error[] = $lang->forenwiki_error_wikitext;
                }


                if (empty($errors)) {
                    $wikisort = (int) $mybb->input['wikisort'];
                    $wikicat = (int) $mybb->input['wikicat'];
                    $wikilinktitle = $db->escape_string($mybb->input['wikilinktitle']);
                    $wikilink = $db->escape_string($mybb->input['wikilink']);
                    $wikititle = $db->escape_string($mybb->input['wikititle']);
                    $wikitext = $db->escape_string($mybb->input['wikitext']);
                    $uid = (int) $mybb->user['uid'];
                    $wid = (int) $mybb->input['wid'];

                    $edit_entry = array(
                        "cid" => $wikicat,
                        "sort" => $wikisort,
                        "linktitle" => $wikilinktitle,
                        "link" => $wikilink,
                        "wikititle" => $wikititle,
                        "wikitext" => $wikitext,
                        "uid" => $uid,
                        "accepted" => 1
                    );

                    $db->update_query("wiki_entries", $edit_entry, "wid = {$wid}");

                    $mybb->input['module'] = "forenwiki";
                    $mybb->input['action'] = $lang->forenwiki_edit_solved;
                    log_admin_action(htmlspecialchars_uni($mybb->input['wikitext']));

                    flash_message($lang->forenwiki_edit_solved, 'success');
                    admin_redirect("index.php?module=config-forenwiki");

                }

            }


            $page->add_breadcrumb_item($lang->forenwiki_edit_entry);
            $page->extra_header .= <<<EOF
                
                <link rel="stylesheet" href="../jscripts/sceditor/themes/mybb.css" type="text/css" media="all" />
                <script type="text/javascript" src="../jscripts/sceditor/jquery.sceditor.bbcode.min.js?ver=1832"></script>
                <script type="text/javascript" src="../jscripts/bbcodes_sceditor.js?ver=1832"></script>
                <script type="text/javascript" src="../jscripts/sceditor/plugins/undo.js?ver=1832"></script> 
                EOF;


            // Build options header
            $page->output_header($lang->forenwiki_manage . " - " . $lang->forenwiki_overview);

            //Übersichtsseite über alle Stops
            $sub_tabs['forenwiki'] = [
                "title" => $lang->forenwiki_overview_entries,
                "link" => "index.php?module=config-forenwiki",
                "description" => $lang->forenwiki_overview_entries_desc
            ];
            //Neuen Kategorie hinzufügen
            $sub_tabs['forenwiki_edit_entry'] = [
                "title" => $lang->forenwiki_edit_entry,
                "link" => "index.php?module=config-forenwiki&amp;action=edit_entry",
                "description" => $lang->forenwiki_edit_entry_desc
            ];

            $page->output_nav_tabs($sub_tabs, 'forenwiki_edit_entry');

            // Get the data
            $wid = $mybb->get_input('wid', MyBB::INPUT_INT);
            $query = $db->simple_select("wiki_entries", "*", "wid={$wid}");
            $edit_entry = $db->fetch_array($query);

            // Erstellen des "Formulars"
            $form = new Form("index.php?module=config-forenwiki&amp;action=edit_entry", "post", "", 1);
            echo $form->generate_hidden_field('wid', $wid);

            $form_container = new FormContainer($lang->forenwiki_edit_entry);
            $form_container->output_row(
                $lang->forenwiki_wikititle . "<em>*</em>",
                $form->generate_text_box('wikititle', htmlspecialchars_uni($edit_entry['wikititle']))
            );

            $query = $db->write_query("SELECT * FROM `" . TABLE_PREFIX . "wiki_categories`");
            $options = array();
            //Wenn es welche gibt: 
            if (mysqli_num_rows($query) > 0) {
                while ($output = $db->fetch_array($query)) {
                    $options[$output['cid']] = $output['category'];
                }
                $form_container->output_row($lang->forenwiki_wikicat . " <em>*</em>", '', $form->generate_select_box('wikicat', $options, $edit_entry['cid'], array($mybb->get_input('wikicat', MyBB::INPUT_INT)), array('id' => 'wikicat')), 'wikicat');
            }

            $form_container->output_row(
                $lang->forenwiki_wikisort . " <em>*</em>",
                $lang->forenwiki_wikisort_desc,
                $form->generate_numeric_field('wikisort', $edit_entry['sort'])
            );
            $form_container->output_row(
                $lang->forenwiki_wikilink_title . " <em>*</em>",
                $lang->forenwiki_wikilink_title_desc,
                $form->generate_text_box('wikilinktitle', $edit_entry['linktitle'])
            );

            $form_container->output_row(
                $lang->forenwiki_wikilink . " <em>*</em>",
                $lang->forenwiki_wikilink_desc,
                $form->generate_text_box('wikilink', $edit_entry['link'])
            );

            $text_editor = $form->generate_text_area(
                'wikitext',
                $edit_entry['wikitext'],
                array(
                    'id' => 'wikitext',
                    'rows' => '25',
                    'cols' => '70',
                    'style' => 'height: 400px; width: 75%'
                )
            );
            $text_editor .= build_mycode_inserter('wikitext');
            $form_container->output_row(
                $lang->forenwiki_wikitext . " <em>*</em>",
                $lang->forenwiki_wikitext_desc,
                $text_editor,
                'text'
            );


            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->forenwiki_wiki_submit);
            $form->output_submit_wrapper($buttons);
            $form->end();
            $page->output_footer();

            exit;
        }

        // Eintrag löschen

        if ($mybb->input['action'] == "delete_entry") {
            // Get the data
            $wid = $mybb->get_input('wid', MyBB::INPUT_INT);
            $query = $db->simple_select("wiki_entries", "*", "wid={$wid}");
            $delete_entry = $db->fetch_array($query);

            if (empty($wid)) {
                flash_message($lang->forenwiki_error_option, 'error');
                admin_redirect("index.php?module=config-forenwiki");

            }
            // Cancel button pressed?
            if (isset($mybb->input['no']) && $mybb->input['no']) {
                admin_redirect("index.php?module=config-forenwiki");
            }

            if (!verify_post_check($mybb->input['my_post_key'])) {
                flash_message($lang->invalid_post_verify_key2, 'error');
                admin_redirect("index.php?module=config-forenwiki");
            } else {
                if ($mybb->request_method == "post") {

                    $db->delete_query("wiki_entries", "wid='{$wid}'");
                    $mybb->input['module'] = "forenwiki";
                    $mybb->input['action'] = $lang->forenwiki_delete_entry_solved;
                    log_admin_action(htmlspecialchars_uni($delete_entry['stoptitel']));

                    flash_message($lang->forenwiki_delete_entry_solved, 'success');
                    admin_redirect("index.php?module=config-forenwiki");
                } else {

                    $page->output_confirm_action(
                        "index.php?module=config-forenwiki&amp;action=delete_entry&amp;wid={$wid}",
                        $lang->forenwiki_delete_entry_question
                    );
                }
            }
            exit;
        }
    }
}

function forenwiki_misc()
{
    global $mybb, $templates, $lang, $header, $headerinclude, $footer, $lang, $parser, $forenwiki_desc, $wiki_cat, $category, $db, $wiki_addentry, $catoption, $newentry_alert, $ownentry;
    $lang->load('forenwiki');
    require_once MYBB_ROOT . "inc/class_parser.php";

    $parser = new postParser;
    // Do something, for example I'll create a page using the hello_world_template
    $options = array(
        "allow_html" => 1,
        "allow_mycode" => 1,
        "allow_smilies" => 1,
        "allow_imgcode" => 1,
        "filter_badwords" => 0,
        "nl2br" => 1,
        "allow_videocode" => 0
    );


    // Bauen wir mal unsere Navigation
    $get_all_cats = $db->simple_select(
        "wiki_categories",
        "*",
        "",
        array(
            "order_by" => 'sort',
            "order_dir" => 'ASC'
        )
    );
    while ($cat = $db->fetch_array($get_all_cats)) {
        $category = "";
        $cid = 0;
        $wiki_entry = "";

        $category = $cat['category'];
        $cid = $cat['cid'];

        $get_all_entries = $db->simple_select("wiki_entries", "*", "cid = {$cid} and accepted = '1'", array(
            "order_by" => 'sort',
            "order_dir" => 'ASC'
        ));

        while ($entries = $db->fetch_array($get_all_entries)) {
            $linktitle = "";
            $link = "";

            $linktitle = $entries['linktitle'];
            $link = $entries['link'];
            eval ("\$wiki_entry .= \"" . $templates->get("forenwiki_nav_entry") . "\";");
        }

        eval ("\$wiki_cat .= \"" . $templates->get("forenwiki_nav_cat") . "\";");
    }

    if ($mybb->usergroup['canaddwikipage'] == 1) {
        eval ("\$wiki_addentry = \"" . $templates->get("forenwiki_nav_add") . "\";");
    }

    eval ("\$wiki_nav = \"" . $templates->get("forenwiki_nav") . "\";");

    // Mainside
    if ($mybb->get_input('action') == 'forenwiki') {
        // Do something, for example I'll create a page using the hello_world_template

        // Add a breadcrumb
        add_breadcrumb($lang->forenwiki, "misc.php?action=forenwiki");

        $forenwiki_desc = $parser->parse_message($mybb->settings['wiki_welcometext'], $options);

        eval ("\$page = \"" . $templates->get("forenwiki") . "\";");
        output_page($page);
    }

    // Unsere Wikieinträge

    // Erstmal eine neue "main" Seite erstellen
    $wikientry = isset($mybb->input['wikientry']);
    if ($wikientry) {
        // wir holen uns mal die Infos
        $wid = 0;
        $wikititle = "";
        $wikitext = "";
        $query = $db->query("SELECT *
        FROM " . TABLE_PREFIX . "wiki_entries
        where link = '" . $wikientry . "'
        and accepted = 1
        ");

        $get_infos = $db->fetch_array($query);
        $wid = $get_infos['wid'];
        $wikititle = $get_infos['wikititle'];

        // Navigation bauen
        switch ($mybb->input['wikientry']) {
            case $wikientry;
                add_breadcrumb($wikititle);
                break;
        }

        // Text noch so machen, das er hübsch dargestellt wird
        $wikitext = $parser->parse_message($get_infos['wikitext'], $options);

        eval ("\$page = \"" . $templates->get("forenwiki_entry") . "\";");
        output_page($page);
    }

    if ($mybb->get_input('action') == 'add_entry') {
        // Do something, for example I'll create a page using the hello_world_template

        if ($mybb->user['uid'] == 0) {
            error_no_permission();
        } elseif ($mybb->usergroup['canaddwikipage'] == 0) {
            error_no_permission();
        }
        // Add a breadcrumb
        add_breadcrumb($lang->forenwiki_add_entry, "misc.php?action=add_entry");
        $uid = $mybb->user['uid'];

        $yournewentry = $db->fetch_field($db->simple_select("wiki_entries", "COUNT(*) as new_wiki", "accepted ='0' and uid ='{$uid}'", array("Limit" => 1)), "new_wiki");

        if ($yournewentry > 0) {
            eval ("\$newentry_alert = \"" . $templates->get("forenwiki_newforenwiki_acceptentry") . "\";");
        }

        $get_all_cats = $db->simple_select(
            "wiki_categories",
            "*",
            "",
            array(
                "order_by" => 'sort',
                "order_dir" => 'ASC'
            )
        );
        while ($cat = $db->fetch_array($get_all_cats)) {
            $category = "";
            $cid = 0;

            $category = $cat['category'];
            $cid = $cat['cid'];
            $catoption .= "<option value='{$cid}'>{$category}</option>";

        }


        if (isset($_POST['submitentry'])) {
            if ($mybb->usergroup['canmodcp'] == 1) {
                $accepted = 1;
            } else {
                $accepted = 0;
            }

            $wikititle = $db->escape_string($_POST['wikititle']);
            $wikicat = (int) $_POST['cid'];
            $wikisort = isset($_POST['sort']);
            $linktitle = $db->escape_string($_POST['linktitle']);
            $link = $db->escape_string($_POST['link']);
            $wikitext = $db->escape_string($_POST['wikitext']);

            $new_entry = array(
                "cid" => $wikicat,
                "sort" => $wikisort,
                "linktitle" => $linktitle,
                "link" => $link,
                "wikititle" => $wikititle,
                "uid" => $mybb->user['uid'],
                "wikitext" => $wikitext,
                "accepted" => $accepted
            );

            $db->insert_query("wiki_entries", $new_entry);
            redirect("misc.php?action=add_entry");
        }

        eval ("\$page = \"" . $templates->get("forenwiki_add_entry") . "\";");
        output_page($page);
    }

    // Eigene einträge

    if ($mybb->get_input('action') == 'own_wikientries') {
        add_breadcrumb($lang->forenwiki_ownentries, "misc.php?action=own_wikientries");
        $uid = $mybb->user['uid'];

        $all_status = array(
            "0" => $lang->forenwiki_openownentries,
            "-1" => $lang->forenwiki_denyownentries,
            "1" => $lang->forenwiki_acceptownentries
        );

        foreach ($all_status as $status => $entrystatus) {

            $get_allentries = $db->query("SELECT *
            FROM " . TABLE_PREFIX . "wiki_entries we
            left join " . TABLE_PREFIX . "wiki_categories wc
            on (we.cid = wc.cid)
            where we.accepted = '{$status}'
            and we.uid = '{$uid}'
            order by wc.sort ASC, wc.category ASC
            ");
            $ownentry = "";

            while ($entry = $db->fetch_array($get_allentries)) {
                $wikititle = "";
                $wikicat = "";
                $wikilink = "";
                $wikioption = "";

                $wikititle = $entry['wikititle'];
                $wikicat = $entry['category'];
                if ($status == 1) {
                    $wikilink = "<a href='misc.php?wikientry={$entry['link']}'>{$entry['linktitle']}</a>";
                } else {
                    $wikilink = $lang->forenwiki_ownnolink;
                }

                $wikioption = "<a href='misc.php?action=edit_entry&wid={$entry['wid']}'>{$lang->forenwiki_ownoption}</a>";
                eval ("\$ownentry .= \"" . $templates->get("forenwiki_ownentries_entries") . "\";");
            }

            eval ("\$statusentry .= \"" . $templates->get("forenwiki_ownentries_status") . "\";");
        }

        eval ("\$page = \"" . $templates->get("forenwiki_ownentries") . "\";");
        output_page($page);
    }

    // Eintragbearbeiten
    if ($mybb->get_input('action') == 'edit_entry') {

        $wid = $mybb->get_input('wid');

        $get_entryinfos = $db->fetch_array($db->simple_select("wiki_entries", "*", "wid = '{$wid}'"));

        $wikititle = "";
        $sort = 0;
        $linktitle = "";
        $link = "";
        $wikitext = "";

        $get_all_cats = $db->simple_select(
            "wiki_categories",
            "*",
            "",
            array(
                "order_by" => 'sort',
                "order_dir" => 'ASC'
            )
        );
        while ($cat = $db->fetch_array($get_all_cats)) {
            $category = "";
            $cid = 0;
            $selected = "";
            $category = $cat['category'];
            $cid = $cat['cid'];

            if ($cid == $get_entryinfos['cid']) {
                $selected = "selected";
            }
            $catoption .= "<option value='{$cid}' {$selected}>{$category}</option>";

        }


        $wikititle = $get_entryinfos['wikititle'];
        $sort = $get_entryinfos['sort'];
        $linktitle = $get_entryinfos['linktitle'];
        $link = $get_entryinfos['link'];
        $wikitext = $get_entryinfos['wikitext'];


        if ($mybb->input['editentry']) {
            if ($mybb->usergroup['canmodcp'] == 1) {
                $accepted = 1;
            } else {
                $accepted = 0;
            }

            $wikititle = $db->escape_string($mybb->input['wikititle']);
            $wikicat = (int) $mybb->input['cid'];
            $wikisort = isset($mybb->input['sort']);
            $linktitle = $db->escape_string($mybb->input['linktitle']);
            $link = $db->escape_string($mybb->input['link']);
            $wikitext = $db->escape_string($mybb->input['wikitext']);

            $edit_entry = array(
                "cid" => $wikicat,
                "sort" => $wikisort,
                "linktitle" => $linktitle,
                "link" => $link,
                "wikititle" => $wikititle,
                "uid" => $mybb->user['uid'],
                "wikitext" => $wikitext,
                "accepted" => $accepted
            );

            $db->update_query("wiki_entries", $edit_entry, "wid = '{$wid}'");
            redirect("misc.php?action=own_wikientries");
        }

        $lang->forenwiki_edit_entry = $lang->sprintf($lang->forenwiki_edit_entry, $wikititle);
        add_breadcrumb($lang->forenwiki_edit_entry, "misc.php?action=edit_entry");


        eval ("\$page = \"" . $templates->get("forenwiki_editentries") . "\";");
        output_page($page);
    }

}

function forenwiki_global()
{
    global $db, $mybb, $templates, $lang, $alert_newentry, $global_newentry_alert, $menu_forenwiki;
    $lang->load('forenwiki');

    $newwikientries = $db->fetch_field($db->simple_select("wiki_entries", "COUNT(*) as newentry", "accepted = '0'", array("Limit" => 1)), "newentry");

    if ($newwikientries > 0 and $mybb->usergroup['canmodcp'] == 1) {
        if ($newwikientries == 1) {
            $entry = $lang->forenwiki_alert_single;
            $before = "ist";
        } else {
            $entry = $lang->forenwiki_alert_multi;
            $before = "sind";
        }
        $alert_newentry = $lang->sprintf($lang->forenwiki_alert_newentry, $before, $newwikientries, $entry);
        eval ("\$global_newentry_alert = \"" . $templates->get("forenwiki_newentry_alert") . "\";");
    }

    eval ("\$menu_forenwiki = \"" . $templates->get("forenwiki_menu") . "\";");
}


function forenwiki_modcp_nav()
{
    global $nav_forenwiki, $templates, $lang;
    $lang->load('forenwiki');
    eval ("\$nav_forenwiki = \"" . $templates->get("forenwiki_modcp_nav") . "\";");
}

function forenwiki_modcp()
{
    global $mybb, $templates, $lang, $header, $headerinclude, $footer, $modcp_nav, $db, $theme;
    $lang->load('forenwiki');
    require_once MYBB_ROOT . "inc/class_parser.php";
    $parser = new postParser;
    // Do something, for example I'll create a page using the hello_world_template
    $options = array(
        "allow_html" => 1,
        "allow_mycode" => 1,
        "allow_smilies" => 1,
        "allow_imgcode" => 1,
        "filter_badwords" => 0,
        "nl2br" => 1,
        "allow_videocode" => 0
    );

    if ($mybb->get_input('action') == 'forenwiki_control') {

        // Add a breadcrumb
        add_breadcrumb($lang->forenwiki_modcp, "modcp.php?action=forenwiki_control");

        $query = $db->query("SELECT *
        FROM " . TABLE_PREFIX . "wiki_entries we
        LEFT JOIN " . TABLE_PREFIX . "wiki_categories wc
        on (we.cid = wc.cid)
        LEFT JOIN " . TABLE_PREFIX . "users u
        on (we.uid = u.uid)
        where accepted = '0'
        ");

        while ($row = $db->fetch_array($query)) {
            $wikititle = "";
            $wikicat = "";
            $wikiauthor = "";
            $wikitext = "";
            $wikioption = "";
            $wid = 0;

            $wikititle = $row['wikititle'];
            $wikicat = $row['category'];
            $username = format_name($row['username'], $row['usergroup'], $row['displaygroup']);
            $wikiauthor = build_profile_link($username, $row['uid']);
            $wid = $row['wid'];
            $wikitext = $parser->parse_message($row['wikitext'], $options);
            $wikioption = "<a href='modcp.php?action=forenwiki_control&accept_entry={$wid}'>{$lang->forenwiki_modcp_accept}</a> // <a href='modcp.php?action=forenwiki_control&deny_entry={$wid}'>{$lang->forenwiki_modcp_deny}</a>";
            eval ("\$modcp_newentry .= \"" . $templates->get("forenwiki_modcp_entry") . "\";");
        }

        $accept_entry = $mybb->input['accept_entry'];
        if ($accept_entry) {

            $infos = $db->fetch_array($db->simple_select("wiki_entries", "*", "wid = '{$accept_entry}'"));
            $uid = $infos['uid'];
            $wikititle = $infos['wikititle'];
            $link = $infos['link'];

            if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
                $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('forenwiki_acceptentry');
                if ($alertType != NULL && $alertType->getEnabled()) {
                    $alert = new MybbStuff_MyAlerts_Entity_Alert((int) $uid, $alertType);
                    $alert->setExtraDetails([
                        'wikititle' => $wikititle,
                        'link' => $link
                    ]);
                    MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
                }
            }

            $edit_entry = array(
                "accepted" => 1
            );

            $db->update_query("wiki_entries", $edit_entry, "wid = {$accept_entry}");
            redirect("modcp.php?action=forenwiki_control");
        }


        $deny_entry = $mybb->input['deny_entry'];
        if ($deny_entry) {

            $infos = $db->fetch_array($db->simple_select("wiki_entries", "*", "wid = '{$deny_entry}'"));
            $uid = $infos['uid'];
            $wikititle = $infos['wikititle'];
            $link = $infos['link'];

            if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
                $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('forenwiki_denyentry');
                if ($alertType != NULL && $alertType->getEnabled()) {
                    $alert = new MybbStuff_MyAlerts_Entity_Alert((int) $uid, $alertType);
                    $alert->setExtraDetails([
                        'wikititle' => $wikititle,
                        'link' => $link
                    ]);
                    MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
                }
            }

            $edit_entry = array(
                "accepted" => -1
            );

            $db->update_query("wiki_entries", $edit_entry, "wid = {$deny_entry}");
            redirect("modcp.php?action=forenwiki_control");
        }

        eval ("\$page = \"" . $templates->get("forenwiki_modcp") . "\";");
        output_page($page);
    }
}

function forenwiki_alerts()
{
    global $mybb, $lang;
    $lang->load('forenwiki');


    /**
     * Alert, wenn die Entry angenommen wurde
     */
    class MybbStuff_MyAlerts_Formatter_AcceptEntryFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
    {
        /**
         * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
         *
         * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
         *
         * @return string The formatted alert string.
         */
        public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->lang->sprintf(
                $this->lang->forenwiki_acceptentry,
                $outputAlert['from_user'],
                $alertContent['wikititle'],
                $outputAlert['dateline']
            );
        }


        /**
         * Init function called before running formatAlert(). Used to load language files and initialize other required
         * resources.
         *
         * @return void
         */
        public function init()
        {
        }

        /**
         * Build a link to an alert's content so that the system can redirect to it.
         *
         * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
         *
         * @return string The built alert, preferably an absolute link.
         */
        public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/misc.php?wikientry=' . $alertContent['link'];
        }
    }

    if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

        if (!$formatterManager) {
            $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
        }

        $formatterManager->registerFormatter(
            new MybbStuff_MyAlerts_Formatter_AcceptEntryFormatter($mybb, $lang, 'forenwiki_acceptentry')
        );
    }

    /**
     * Alert, wenn die Entry angenommen wurde
     */
    class MybbStuff_MyAlerts_Formatter_denyEntryFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
    {
        /**
         * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
         *
         * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
         *
         * @return string The formatted alert string.
         */
        public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->lang->sprintf(
                $this->lang->forenwiki_denyentry,
                $outputAlert['from_user'],
                $alertContent['wikititle'],
                $outputAlert['dateline']
            );
        }


        /**
         * Init function called before running formatAlert(). Used to load language files and initialize other required
         * resources.
         *
         * @return void
         */
        public function init()
        {
        }

        /**
         * Build a link to an alert's content so that the system can redirect to it.
         *
         * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
         *
         * @return string The built alert, preferably an absolute link.
         */
        public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
        {
            $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/misc.php?action=own_wikientries';
        }
    }

    if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
        $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

        if (!$formatterManager) {
            $formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
        }

        $formatterManager->registerFormatter(
            new MybbStuff_MyAlerts_Formatter_denyEntryFormatter($mybb, $lang, 'forenwiki_denyentry')
        );
    }

}


function forenwiki_user_activity($user_activity)
{
    global $parameters, $user, $db, $side_name;
    if (my_strpos($user['location'], "misc.php?action=forenwiki") !== false) {
        $user_activity['activity'] = "forenwiki";
    }
    if (my_strpos($user['location'], "misc.php?action=add_entry") !== false) {
        $user_activity['activity'] = "add_entry";
    }
    if (my_strpos($user['location'], "misc.php?action=own_wikientries") !== false) {
        $user_activity['activity'] = "own_wikientries";
    }

    // Wikieinträge
    $get_sidename = explode(".php?", $user['location']);

    $split_sn = explode("=", $get_sidename[1]);


    if ($split_sn[0] == "wikientry") {
        $get_link = "wikientry={$split_sn[1]}";
        $user_activity['activity'] = $get_link;
    }

    $get_entry = explode("&", $split_sn[1]);
    if ($get_entry[0] == "edit_entry") {
        $user_activity['activity'] = $get_entry[0];
    }

    return $user_activity;
}

function forenwiki_location_activity($plugin_array)
{
    global $db, $mybb, $lang;
    $lang->load('forenwiki');
    if ($plugin_array['user_activity']['activity'] == "forenwiki") {
        $plugin_array['location_name'] = $lang->forenwiki_wiw;
    }
    if ($plugin_array['user_activity']['activity'] == "add_entry") {
        $plugin_array['location_name'] = $lang->forenwiki_wiw_addentry;
    }
    if ($plugin_array['user_activity']['activity'] == "own_wikientries") {
        $plugin_array['location_name'] = $lang->forenwiki_wiw_ownentry;
    }
    if ($plugin_array['user_activity']['activity'] == "edit_entry") {
        $plugin_array['location_name'] = $lang->forenwiki_wiki_edit;
    }

    $split_sidename = explode("=", $plugin_array['user_activity']['activity']);
    $sidename = $split_sidename[0];
    $entry = $split_sidename[1];
    if ($sidename == "wikientry") {
        $linktitle = $db->fetch_field($db->simple_select("wiki_entries", "linktitle", "link = '{$entry}'"), "linktitle");
        $lang->forenwiki_wiki_entry = $lang->sprintf($lang->forenwiki_wiki_entry, $entry, $linktitle);
        $plugin_array['location_name'] = $lang->forenwiki_wiki_entry;
    }

    return $plugin_array;
}
