<?php

namespace Budabot\User\Modules;

/**
 * @Instance
 *
 * Commands this controller contains:
 *	@DefineCommand(
 *		command     = 'whatbuffsfroob',
 *		accessLevel = 'all',
 *		description = 'Find items or nanos for froobs that buff an ability or skill',
 *		alias       = 'wbf',
 *		help        = 'whatbuffsfroob.txt'
 *	)
 */
class WBFController {
	
	public $moduleName;

	/**
	 * @var \Budabot\Core\Http $http
	 * @Inject
	 */
	public $http;

	/**
	 * @var \Budabot\Core\Text $text
	 * @Inject
	 */
	public $text;
	
	/**
	 * @var \Budabot\Core\DB $db
	 * @Inject
	 */
	public $db;
	
	/**
	 * @var \Budabot\Core\Util $util
	 * @Inject
	 */
	public $util;

	/**
	 * @var \Budabot\Core\CommandAlias $commandAlias
	 * @Inject
	 */
	public $commandAlias;
	
	/**
	 * @var \Budabot\Core\ItemsController $itemsController
	 * @Inject
	 */
	public $itemsController;
	
	/**
	 * @var \Budabot\Core\LoggerWrapper $logger
	 * @Logger
	 */
	public $logger;
	
	/** @Setup */
	public function setup() {
		$this->db->loadSQLFile($this->moduleName, "item_paid_only");
	}

	/**
	 * @HandlesCommand("whatbuffsfroob")
	 * @Matches("/^whatbuffsfroobs?$/i")
	 */
	public function whatbuffsFroobCommand($message, $channel, $sender, $sendto, $args) {
		$blob = '';
		$data = $this->db->query("SELECT DISTINCT name FROM skills ORDER BY name ASC");
		forEach ($data as $row) {
			$blob .= $this->text->makeChatcmd($row->name, "/tell <myname> whatbuffsfroob $row->name") . "\n";
		}
		$msg = $this->text->makeBlob("WhatBuffsFroob - Choose Skill", $blob);
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("whatbuffsfroob")
	 * @Matches("/^whatbuffsfroobs? (arms|back|chest|deck|feet|fingers|hands|head|hud|legs|nanoprogram|neck|shoulders|unknown|util|weapon|wrists)$/i")
	 */
	public function whatbuffs2Command($message, $channel, $sender, $sendto, $args) {
		$type = ucfirst(strtolower($args[1]));
		
		if ($this->verifySlot($type)) {
			$sql = "
				SELECT s.name AS skill, COUNT(1) AS num
				FROM aodb
				JOIN item_types i ON aodb.highid = i.item_id
				JOIN item_buffs b ON aodb.highid = b.item_id
				JOIN skills s ON b.attribute_id = s.id
				LEFT JOIN item_paid_only p ON p.item_id=aodb.lowid
				WHERE i.item_type = ? AND p.item_id IS null
				GROUP BY skill
				HAVING num > 0
				ORDER BY skill ASC
			";
			$data = $this->db->query($sql, $type);
			$blob = '';
			forEach ($data as $row) {
				$blob .= $this->text->makeChatcmd(ucfirst($row->skill), "/tell <myname> whatbuffsfroob $type $row->skill") . " ($row->num)\n";
			}
			$msg = $this->text->makeBlob("WhatBuffsFroob $type - Choose Skill", $blob);
		} else {
			$msg = "Could not find any items of type <highlight>$type<end>.";
		}
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("whatbuffsfroob")
	 * @Matches("/^whatbuffsfroob (arms|back|chest|deck|feet|fingers|hands|head|hud|legs|nanoprogram|neck|shoulders|unknown|util|weapon|wrists) (.+)$/i")
	 */
	public function whatbuffs3Command($message, $channel, $sender, $sendto, $args) {
		$type = $args[1];
		$skill = $args[2];

		if ($this->verifySlot($type)) {
			$msg = $this->showSearchResults($type, $skill);
		} else {
			$msg = "Could not find any items of type <highlight>$type<end> for skill <highlight>$skill<end>.";
		}
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("whatbuffsfroob")
	 * @Matches("/^whatbuffsfroob (.+) (arms|back|chest|deck|feet|fingers|hands|head|hud|legs|nanoprogram|neck|shoulders|unknown|util|weapon|wrists)$/i")
	 */
	public function whatbuffs4Command($message, $channel, $sender, $sendto, $args) {
		$skill = $args[1];
		$type = $args[2];

		if ($this->verifySlot($type)) {
			$msg = $this->showSearchResults($type, $skill);
		} else {
			$msg = "Could not find any items of type <highlight>$type<end> for skill <highlight>$skill<end>.";
		}
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("whatbuffsfroob")
	 * @Matches("/^whatbuffsfroob (.+)$/i")
	 */
	public function whatbuffs5Command($message, $channel, $sender, $sendto, $args) {
		$skill = $args[1];

		$data = $this->searchForSkill($skill);
		$count = count($data);

		if ($count == 0) {
			$msg = "Could not find skill <highlight>$skill<end>.";
		} elseif ($count > 1) {
			$blob .= "Choose a skill:\n\n";
			forEach ($data as $row) {
				$blob .= $this->text->makeChatcmd(ucfirst($row->name), "/tell <myname> whatbuffsfroob $row->name") . "\n";
			}
			$msg = $this->text->makeBlob("WhatBuffsFroob - Choose Skill", $blob);
		} else {
			$skillId = $data[0]->id;
			$skillName = $data[0]->name;
			$sql = "
				SELECT i.item_type, COUNT(1) AS num
				FROM aodb
				JOIN item_types i ON aodb.highid = i.item_id
				JOIN item_buffs b ON aodb.highid = b.item_id
				JOIN skills s ON b.attribute_id = s.id
				LEFT JOIN item_paid_only p ON p.item_id=aodb.lowid
				WHERE s.id = ? AND p.item_id IS null
				GROUP BY item_type
				HAVING num > 0
				ORDER BY item_type ASC
			";
			$data = $this->db->query($sql, $skillId);
			$blob = '';
			forEach ($data as $row) {
				$blob .= $this->text->makeChatcmd(ucfirst($row->item_type), "/tell <myname> whatbuffsfroob $row->item_type $skillName") . " ($row->num)\n";
			}
			$msg = $this->text->makeBlob("WhatBuffsFroob $skillName - Choose Type", $blob);
		}
		$sendto->reply($msg);
	}
	
	public function getSearchResults($category, $skill) {
		$sql = "
			SELECT aodb.*, b.amount
			FROM aodb
			JOIN item_types i ON aodb.highid = i.item_id
			JOIN item_buffs b ON aodb.highid = b.item_id
			JOIN skills s ON b.attribute_id = s.id
			LEFT JOIN item_paid_only p ON p.item_id=aodb.lowid
			WHERE i.item_type = ? and s.id = ? and p.item_id IS null
			ORDER BY b.amount DESC;
		";
		$data = $this->db->query($sql, $category, $skill->id);

		$result = $this->formatItems($data);

		if ($result === null) {
			$msg = "No items found of type <highlight>$category<end> that buff <highlight>$skill->name<end>.";
		} else {
			list($count, $blob) = $result;
			$msg = $this->text->makeBlob("WhatBuffsFroob - $category $skill->name ($count)", $blob);
		}
		return $msg;
	}

	public function verifySlot($type) {
		$type = ucfirst(strtolower($type));
		$row = $this->db->queryRow("SELECT 1 FROM item_types WHERE item_type = ? LIMIT 1", $type);
		return $row !== null;
	}
	
	public function searchForSkill($skill) {
		// check for exact match first, in order to disambiguate
		// between Bow and Bow special attack
		$results = $this->db->query("SELECT DISTINCT id, name FROM skills WHERE name LIKE ?", $skill);
		if (count($results) == 1) {
			return $results;
		}
		
		$tmp = explode(" ", $skill);
		list($query, $params) = $this->util->generateQueryFromParams($tmp, 'name');
		
		return $this->db->query("SELECT DISTINCT id, name FROM skills WHERE $query", $params);
	}
	
	public function formatItems($items) {
		$blob = '';
		forEach ($items as $item) {
			$blob .= $this->text->makeItem($item->lowid, $item->highid, $item->highql, $item->name) . " ($item->amount)\n";
		}

		$count = count($items);
		if ($count > 0) {
			return array($count, $blob);
		} else {
			return null;
		}
	}
	
	public function showSearchResults($category, $skill) {
		$category = ucfirst(strtolower($category));
		
		$data = $this->searchForSkill($skill);
		$count = count($data);
		
		if ($count == 0) {
			$msg = "Could not find any skills matching <highlight>$skill<end>.";
		} elseif ($count == 1) {
			$row = $data[0];
			$msg = $this->getSearchResults($category, $row);
		} else {
			$blob = '';
			forEach ($data as $row) {
				$blob .= $this->text->makeChatcmd(ucfirst($row->skill), "/tell <myname> whatbuffsfroob $category $row->skill") . "\n";
			}
			$msg = $this->text->makeBlob("WhatBuffsFroob - Choose Skill", $blob);
		}
		
		return $msg;
	}
}
