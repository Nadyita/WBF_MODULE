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
	 * @var \Budabot\Core\CommandManager $commandManager
	 * @Inject
	 */
	public $commandManager;
	
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
	 * @Matches("/^whatbuffsfroobs? (arms|back|chest|deck|feet|fingers|hands|head|hud|legs|nanoprogram|neck|shoulders|unknown|util|weapon|wrists|use|contract|tower)$/i")
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
	 * @Matches("/^whatbuffsfroob (arms|back|chest|deck|feet|fingers|hands|head|hud|legs|nanoprogram|neck|shoulders|unknown|util|weapon|wrists|use|contract|tower) (.+)$/i")
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
	 * @Matches("/^whatbuffsfroob (.+) (arms|back|chest|deck|feet|fingers|hands|head|hud|legs|nanoprogram|neck|shoulders|unknown|util|weapon|wrists|use|contract|tower)$/i")
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
				SELECT item_type, COUNT(*) AS num FROM (
					SELECT it.item_type
					FROM aodb a
					JOIN item_types it ON a.highid = it.item_id
					JOIN item_buffs ib ON a.highid = ib.item_id
					JOIN skills s ON ib.attribute_id = s.id
					LEFT JOIN item_paid_only p ON p.item_id=a.lowid
					WHERE s.id = ? AND ib.amount > 0 AND p.item_id IS NULL
					GROUP BY a.name,a.lowql,a.highql,ib.amount
					HAVING ib.amount > 0

					UNION ALL

					SELECT 'Nanoprogram' AS item_type
					FROM buffs b
					JOIN item_buffs ib ON ib.item_id = b.id
					JOIN skills s ON ib.attribute_id = s.id
					LEFT JOIN item_paid_only p ON p.item_id=b.id
					WHERE s.id = ? AND ib.amount > 0 AND p.item_id IS NULL
				) AS FOO
				GROUP BY item_type
				ORDER BY item_type ASC
			";
			$data = $this->db->query($sql, $skillId, $skillId);
			$blob = '';
			forEach ($data as $row) {
				$blob .= $this->text->makeChatcmd(ucfirst($row->item_type), "/tell <myname> whatbuffsfroob $row->item_type $skillName") . " ($row->num)\n";
			}
			$msg = $this->text->makeBlob("WhatBuffsFroob $skillName - Choose Type", $blob);
		}
		$sendto->reply($msg);
	}
	
	public function getSearchResults($category, $skill) {
		if ($category === 'Nanoprogram') {
			$sql = "
				SELECT buffs.*, b.amount,aodb.lowid,aodb.highid,aodb.lowql,aodb.name AS use_name
				FROM buffs
				JOIN item_buffs b ON buffs.id = b.item_id
				JOIN skills s ON b.attribute_id = s.id
				LEFT JOIN aodb ON (aodb.lowid=buffs.use_id)
				LEFT JOIN item_paid_only p ON p.item_id=aodb.lowid
				WHERE s.id = ? AND b.amount > 0 AND p.item_id IS NULL
				ORDER BY b.amount DESC, buffs.name ASC
			";
			$data = $this->db->query($sql, $skill->id);
			$result = $this->formatBuffs($data);
		} else {
			$sql = "
				SELECT aodb.*, b.amount,b2.amount AS low_amount, wa.multi_m, wa.multi_r
				FROM aodb
				JOIN item_types i ON aodb.highid = i.item_id
				JOIN item_buffs b ON aodb.highid = b.item_id
				LEFT JOIN item_buffs b2 ON aodb.lowid = b2.item_id
				LEFT JOIN weapon_attributes wa ON aodb.highid = wa.id
				JOIN skills s ON b.attribute_id = s.id AND b2.attribute_id = s.id
				LEFT JOIN item_paid_only p ON p.item_id=aodb.lowid
				WHERE i.item_type = ? AND s.id = ? AND p.item_id IS NULL AND b.amount > 0
				GROUP BY aodb.name,aodb.lowql,aodb.highql,b.amount,b2.amount,wa.multi_m,wa.multi_r
				ORDER BY b.amount DESC, name DESC
			";
			$data = $this->db->query($sql, $category, $skill->id);
			$result = $this->formatItems($data);
		}

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
		$maxBuff = 0;
		forEach ($items as $item) {
			if (strncmp($item->name, "Universal Advantage - ", 22) === 0) {
				$item->highql = 250;
			}
			if ($item->amount === $item->low_amount) {
				$item->highql = $item->lowql;
			}
			if (
				$item->highql > 250 &&
				strpos($item->name, " Filigree Ring set with a ") !== false
			) {
				$item->amount = $this->util->interpolate($item->lowql, $item->highql, $item->low_amount, $item->amount, 250);
				$item->highql = 250;
			}
			$maxBuff = max($maxBuff, $item->amount);
			$itemMapping[$item->lowid] = $item;
		}
		$ignoreItems = array();
		forEach ($items as $item) {
			if ($item->highid != $item->lowid && array_key_exists($item->highid, $itemMapping)) {
				$item->highid = $itemMapping[$item->highid]->highid;
				$item->highql = $itemMapping[$item->highid]->highql;
				$ignoreItems []= $itemMapping[$item->highid];
			}
		}
		$maxDigits = strlen((string)$maxBuff);
		forEach ($items as $item) {
			if (in_array($item, $ignoreItems, true)) {
				continue;
			}
			$prefix = $this->text->alignNumber($item->amount, $maxDigits, 'highlight');
			$blob .= $prefix . "  ";
			if ($item->multi_m !== null || $item->multi_r !== null) {
				$blob .= "2x ";
			}
			$blob .= $this->text->makeItem($item->lowid, $item->highid, $item->highql, $item->name);
			if ($item->amount > $item->low_amount) {
				$blob .= " ($item->low_amount - $item->amount)";
				if ($this->commandManager->get('bestql')) {
					$link = $this->text->makeItem($item->lowid, $item->highid, 0, $item->name);
					$blob .= " " . $this->text->makeChatcmd(
						"Breakpoints",
						"/tell <myname> bestql $item->lowql $item->low_amount $item->highql $item->amount ".
						$link
					);
				}
			}
			$blob .= "\n";
		}

		$count = count($items);
		if ($count > 0) {
			return array($count, $blob);
		} else {
			return null;
		}
	}

	public function formatBuffs($items) {
		$blob = '';
		$maxBuff = 0;
		forEach ($items as $item) {
			$maxBuff = max($maxBuff, $item->amount);
		}
		$maxDigits = strlen((string)$maxBuff);
		forEach ($items as $item) {
			if ($item->ncu == 999) {
				$item->ncu = 0;
			}
			$prefix = $this->text->alignNumber($item->amount, $maxDigits, 'highlight');
			$blob .= $prefix . "  <a href='itemid://53019/{$item->id}'>{$item->name}</a> ($item->ncu NCU)";
			if ($item->lowid > 0) {
				$blob .= " (from " . $this->text->makeItem($item->lowid, $item->highid, $item->lowql, $item->use_name) . ")";
			}
			$blob .= "\n";
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
