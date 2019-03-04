<?php
class SyncAPI extends Page {

	public function getName() { return "SyncAPI"; }

	public function getURL() { return "/v1.0.0"; }

	private $URLMatch;
	public function isMatch($URL) {
		return preg_match(
			'/projects\/spotifywebplayer\/(v1.0.0)(?:\/(?:track)\/([0-9a-zA-Z]{22}))?/',
			$URL,
			$this->URLMatch
		);
	}

	private function createStatus($code, $reason) {
		$statusCodes = array(
			200 => 'OK',
			201 => 'Created',
			301 => 'Moved Permanently',
			302 => 'Found',
			400 => 'Bad Request',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			409 => 'Conflict',
			429 => 'Too Many Requests',
			500 => 'Internal Error',
			503 => 'Service Unavailable'
		);
		return array(
			'code' => $code,
			'status' => $statusCodes[$code],
			'reason' => $reason
		);
	}

	private function echoJSONResult($err, $results) {
		header('Content-Type: application/json');
		http_response_code($err['code']);
		echo json_encode(array('error' => $err, 'data' => $results));
		exit();
	}

	private function handleInternalError($template) {
		return json_encode(array('error' => $this->createStatus(500, '{errorCode} ({errorName}):{errorDescription}'), 'data' => null));;
	}

	private function isRequestable($type) {
		if (!isset($_SESSION['lastRequest'])) {
			$_SESSION['lastRequest'] = array(
				'insertion' => array(
					'max' => 60, // Insertion 2 minute timeout
					'last' => time()
				),
				'selection' => array(
					'max' => 3, // Selection 3 second timeout
					'last' => time()
				)
			);
			// Prevent people spamming with different cookies
			// they must keep the cookie for 2 minutes before submitting anything!
			return $type == 'selection';
		} else if (isset($_SESSION['lastRequest'][$type])){
			$req = $_SESSION['lastRequest'][$type];
			$isAllowed = (time() - $req['last']) >= $req['max'];
			$_SESSION['lastRequest'][$type]['last'] = time();
			return $isAllowed;
		}
	}
	private function getTotalInsertionsToday() {
		try {
			$sql = "SELECT COUNT(*) FROM SongLyric WHERE User_ID = ? && Time >= now() - INTERVAL 1 DAY";
			$stmt = Engine::getDatabase()->prepare($sql);
			return $stmt->execute(array($_POST['username']));
		} catch (PDOException $e) {
			$this->echoJSONResult(
				$this->createStatus(
					500,
					'A problem occured trying to receive total requests from user'
				)
			);
			exit();
		}
	}

	private function searchURI($uri) {
		try {
			$sql = "SELECT URI as uri, Lyrics as lyrics, SyncInfo as sync, User_ID as username FROM SongLyric WHERE URI = ?";
			$stmt = Engine::getDatabase()->prepare($sql);
			$stmt->execute(array($uri));
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		} catch (PDOException $e) {
			$this->echoJSONResult($this->createStatus(500, $e->getMessage()), null);
			exit();
		}
	}

	private function insertTrackSync() {
		try {
			$sql = "INSERT INTO SongLyric (`URI`, `Lyrics`, `SyncInfo`, `User_ID`) VALUES (?, ?, ?, ?)";
			$stmt = Engine::getDatabase()->prepare($sql);
			$stmt->execute(array($_POST['uri'], stripcslashes($_POST['lyrics']), stripcslashes($_POST['sync']), $_POST['username']));
		} catch (PDOException $e) {
			$this->echoJSONResult($this->createStatus(500, $e->getMessage()), null);
			exit();
		}
	}

	public function run($template) {
		ErrorHandler::setErrorHTML($this->handleInternalError($template));
		try{
			if (isset($_POST['uri'])) {
				// Insert sync track
				if (!$this->isRequestable('insertion')) return $this->echoJSONResult(
					$this->createStatus(
						429,
						"You have been temporarily timed out"
					),
					null
				);
				if ($this->getTotalInsertionsToday() > 100) return $this->echoJSONResult(
					$this->createStatus(
						429,
						'You can only create 100 sync requests a day'
					),
					null
				);
				$this->insertTrackSync();
				$this->echoJSONResult($this->createStatus(201, null), null);
			} else if (isset($this->URLMatch[2])){
				// Search for a track lyrics
				if (!$this->isRequestable('selection')) return $this->echoJSONResult(
					$this->createStatus(
						429,
						'You have been temporarily timed out'
					),
					null
				);
				$search = $this->searchURI($this->URLMatch[2]);
				if ($search){
					$this->echoJSONResult($this->createStatus('200', null), $search);
				} else {
					$this->echoJSONResult($this->createStatus('404', 'No track present'), null);
				}
			} else {
				// Return help!
				$this->echoJSONResult(
					$this->createStatus(200, ''),
					array(
						'endpoints' => array(
							'track' => array(
								'type' => 'endpoint',
								'description' => 'Find a song lyric',
								'format' => 'track/{id}',
								'example' => 'track/4uLU6hMCjMI75M1A2tKUQC',
								'GET' => array(
									'id' => array(
										'type' => 'identifier',
										'description' => 'the indentity of a given track',
										'example' => '4uLU6hMCjMI75M1A2tKUQC'
									)
								),
								'POST' => array(
									'uri' => array(
										'type' => 'variable',
										'description' => 'the identity of the new track',
										'example' => '4uLU6hMCjMI75M1A2tKUQC'
									),
									'username' => array(
										'type' => 'variable',
										'description' => 'the user adding the new details',
										'example' => 'John.Smith'
									),
									'lyrics' => array(
										'type' => 'variable',
										'description' => 'the lyrics belonging to the track uri',
										'example' => 'Hit the road Jack and don\'t cha come back\nNo more no more no more no more\nHit the road Jack and don\'t cha come back\nNo more\nWhat\'d you say'
									),
									'sync' => array(
										'type' => 'variable',
										'description' => 'the metadata relating time to the lyrics'
									)
								)
							)
						)
					)
				);
			}
		} catch (Exception $e) {
			$this->echoJSONResult($this->createStatus(500, $e->getMessage()), null);
		}
	}

	public function show($template) {}
}
?>
