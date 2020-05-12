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
 *
 *	@DefineCommand(
 *		command     = 'wbfreport',
 *		accessLevel = 'all',
 *		description = 'Report an item as unusable for froobs',
 *		help        = 'whatbuffsfroob.txt'
 *	)
 */
class WBFController extends WhatBuffsController {
	
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
		$this->db->loadSQLFile($this->moduleName, "item_not_froob");
	}

	public function whatbuffsCommand($message, $channel, $sender, $sendto, $args) {
	}

	/**
	 * @HandlesCommand("whatbuffsfroob")
	 * @Matches("/^whatbuffsfroobs?$/i")
	 */
	public function whatbuffsFroobCommand($message, $channel, $sender, $sendto, $args) {
		$blob = '';
		$data = $this->db->query("SELECT DISTINCT name FROM skills ORDER BY name ASC");
		foreach ($data as $row) {
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
			foreach ($data as $row) {
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
		parent::whatbuffs3Command($message, $channel, $sender, $sendto, $args);
	}

	/**
	 * @HandlesCommand("whatbuffsfroob")
	 * @Matches("/^whatbuffsfroob (.+) (arms|back|chest|deck|feet|fingers|hands|head|hud|legs|nanoprogram|neck|shoulders|unknown|util|weapon|wrists|use|contract|tower)$/i")
	 */
	public function whatbuffs4Command($message, $channel, $sender, $sendto, $args) {
		parent::whatbuffs4Command($message, $channel, $sender, $sendto, $args);
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
			foreach ($data as $row) {
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
					GROUP BY a.name,it.item_type,a.lowql,a.highql,ib.amount
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
			foreach ($data as $row) {
				$blob .= $this->text->makeChatcmd(ucfirst($row->item_type), "/tell <myname> whatbuffsfroob $row->item_type $skillName") . " ($row->num)\n";
			}
			$msg = $this->text->makeBlob("WhatBuffsFroob $skillName - Choose Type", $blob);
		}
		$sendto->reply($msg);
	}
	
	public function getSearchResults($category, $skill) {
		if ($category === 'Nanoprogram') {
			$sql = "
				SELECT buffs.*, b.amount,aodb.lowid,aodb.highid,aodb.lowql,aodb.name AS use_name, inf.item_id IS NOT NULL as paid
				FROM buffs
				JOIN item_buffs b ON buffs.id = b.item_id
				JOIN skills s ON b.attribute_id = s.id
				LEFT JOIN aodb ON (aodb.lowid=buffs.use_id)
				LEFT JOIN item_paid_only p1 ON p1.item_id=aodb.lowid
				LEFT JOIN item_paid_only p2 ON p2.item_id=buffs.id
				LEFT JOIN item_not_froob inf ON (inf.item_id=aodb.lowid OR inf.item_id=buffs.id)
				WHERE s.id = ? AND b.amount > 0 AND p1.item_id IS NULL AND p2.item_id IS NULL
				ORDER BY b.amount DESC, buffs.name ASC
			";
			$data = $this->db->query($sql, $skill->id);
			$result = $this->formatBuffs($data);
		} else {
			$sql = "
				SELECT aodb.*, b.amount,b2.amount AS low_amount, wa.multi_m, wa.multi_r, inf.item_id IS NOT NULL AS paid
				FROM aodb
				JOIN item_types i ON aodb.highid = i.item_id
				JOIN item_buffs b ON aodb.highid = b.item_id
				LEFT JOIN item_buffs b2 ON aodb.lowid = b2.item_id
				LEFT JOIN weapon_attributes wa ON aodb.highid = wa.id
				JOIN skills s ON b.attribute_id = s.id AND b2.attribute_id = s.id
				LEFT JOIN item_paid_only p ON p.item_id=aodb.lowid
				LEFT JOIN item_not_froob inf ON inf.item_id=aodb.lowid
				WHERE i.item_type = ? AND s.id = ? AND p.item_id IS NULL AND b.amount > 0
				GROUP BY aodb.name,aodb.lowql,aodb.highql,b.amount,b2.amount,wa.multi_m,wa.multi_r
				ORDER BY b.amount DESC, name DESC, aodb.highql DESC
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
			foreach ($data as $row) {
				$blob .= $this->text->makeChatcmd(ucfirst($row->skill), "/tell <myname> whatbuffsfroob $category $row->skill") . "\n";
			}
			$msg = $this->text->makeBlob("WhatBuffsFroob - Choose Skill", $blob);
		}
		
		return $msg;
	}

	/**
	 * @HandlesCommand("wbfreport")
	 * @Matches("/^wbfreport\s+(\d+)$/i")
	 */
	public function whatbuffsFroobReportCommand($message, $channel, $sender, $sendto, $args) {
		$exists = $this->db->queryRow(
			"SELECT COUNT(*) AS `exists` FROM item_not_froob WHERE item_id=?",
			$args[1]
		);
		if ($exists->exists == 0) {
			var_dump("Inserting");
			$this->db->exec(
				"INSERT INTO item_not_froob(item_id) VALUES (?)",
				$args[1]
			);
		}
		$msg = "Thank you, your report will be processed for the next update.";
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("wbfreport")
	 * @Matches("/^wbfreport\s+list$/i")
	 */
	public function whatbuffsFroobReportListCommand($message, $channel, $sender, $sendto, $args) {
		$reportedItems = $this->db->query(
			"SELECT a.* FROM item_not_froob inf JOIN aodb a ON (inf.item_id=a.lowid) ORDER BY a.lowid ASC",
		);
		if (count($reportedItems) === 0) {
			$msg = "There are currently no items reported as unusable by froobs.";
			$sendto->reply($msg);
			return;
		}
		$blob = "";
		foreach ($reportedItems as $item) {
			$blob .= "INSERT INTO item_paid_only (item_id) VALUES (".
				$item->lowid.
				"); -- ".
				$this->text->makeItem($item->lowid, $item->highid, $item->lowql, $item->name).
				"\n";
			if ($item->lowid !== $item->highid) {
				$blob .= "INSERT INTO item_paid_only (item_id) VALUES (".
					$item->highid.
					"); -- ".
					$this->text->makeItem($item->lowid, $item->highid, $item->highql, $item->name).
					"\n";
			}
		}
		$blob .= "\n\n".
			$this->text->makeChatcmd("Delete this list", "/tell <myname> wbfreport clear");
		$msg = $this->text->makeBlob("List of reported non-froob items", $blob);
		if (is_array($msg)) {
			$msg = array_map(function($text) {
				 return "Here is the $text";
			}, $msg);
		} else {
			$msg = "Here is the $msg";
		}
		$sendto->reply($msg);
	}

	/**
	 * @HandlesCommand("wbfreport")
	 * @Matches("/^wbfreport\s+clear$/i")
	 */
	public function whatbuffsFroobReportClearCommand($message, $channel, $sender, $sendto, $args) {
		$this->db->exec("DELETE FROM item_not_froob");
		$msg = "All reported items cleared.";
		$sendto->reply($msg);
	}

	public function showItemLink(\Budabot\Core\DBRow $item, $ql) {
		$itemLink = $this->text->makeItem($item->lowid, $item->highid, $ql, $item->name);
		$reportCmd = $this->text->makeChatcmd("X", "/tell <myname> wbfreport " . $item->lowid);
		if ($item->paid) {
			$reportCmd = "<red>PAID<end>";
		}
		return $itemLink . " [$reportCmd]";
	}
}
