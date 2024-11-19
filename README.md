# forenwiki-2.0

Hier könnt ihr ein eigenes interaktives Forenwikipedia erstellen. Die Navigation wächst mit ihren Kategorien und Einträge. Ihr könnt sie sortieren und eure eigenen Einträge bearbeiten. Das Team muss neue Usereinträge im Modcp absegnen/ablehnen. Einträge werden, auch wenn sie abgelehnt werden, nicht gelöscht, sondern nur einkategorisiert und können vom User bearbeitet werden.<br />
Alle Einträge sind übers ACP vom Team änderbar.

## wichtige Einstellungen
Achte darauf, dass ihr über **Administrator-Berechtigungen** auch jeden Admin die Berechtigung gibt, dass er die Wikieinträge auch verwalten kann.

## DB
- wiki_categories
- wiki_entries

## Templates
- forenwiki 	
- forenwiki_add_entry 	
- forenwiki_editentries 	
- forenwiki_entry 	
- forenwiki_menu 	
- forenwiki_modcp 	
- forenwiki_modcp_entry 	
- forenwiki_modcp_nav 	
- forenwiki_nav 	
- forenwiki_nav_add 	
- forenwiki_nav_cat 	
- forenwiki_nav_entry 	
- forenwiki_newentry_accept 	
- forenwiki_newentry_alert 	
- forenwiki_ownentries 	
- forenwiki_ownentries_entries 	
- forenwiki_ownentries_status

## CSS
**forenwiki.css**
```.wiki_navi{
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
        ```
