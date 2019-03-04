<?php
require('pages/Redirect.php');
require('pages/SyncAPI.php');

class SWP extends Template {

	public function getName() {
		return "SyncAPI (from Spotify Web Player for Linux)";
	}

	public function __construct() {
		parent::__construct();
	}

	public function configure($setup) {
		try {
			$dbh = Engine::getDatabase();
			$dbh->exec("DROP TABLE IF EXISTS SongLyric;");
			$dbh->exec("CREATE TABLE SongLyric (URI VARCHAR(64) NOT NULL, Lyrics text NOT NULL, SyncInfo text NOT NULL, User_ID varchar(64) NOT NULL, Time TIMESTAMP NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=latin1;");
			//$dbh->exec("ALTER TABLE SongLyric ADD UNIQUE (URI);");
		} catch (PDOException $e) {
			$setup->sendStatus(true, array($setup->addName("template-config-error"), "error_message" => $e->getMessage()));
		}
	}

	public function getPages() {
		return array(
			new SyncAPI(),
			new Redirect()
		);
	}
}
return new SWP();
?>
