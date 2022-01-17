<?php
/*
Requires freecolor, can only be run as cli
FreeBSD 9.3+
*/
namespace App;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
define('mem_max_diff', 512);
error_reporting(E_ALL);

$safemode = true; //THIS ENABLES/DISABLES MEMORY CHECK THROUGH OUT THE PROGRAM
define('ITEM_BULK_SIZE', 250000); //ITEM COPY BULK SIZE
$maxMemory = "10G";
if($safemode)
{
    $cFree = get_server_memory_free();
    $maxMemory = ($cFree - mem_max_diff) / 1000;
    $maxMemory = $maxMemory . "G";
    echo "Setting max execution memory usage to " . $maxMemory . "\n";
}
\ini_set("memory_limit", $maxMemory);

function get_server_memory_free()
{
    $bigcmd = shell_exec('freecolor -m -o');
    $arr = explode("\n", $bigcmd);
    $memarr = explode("         ", $arr[1]);
    unset($arr);
    $freearr = explode("\t", $memarr[1]);
    $freeA = explode(" ", $freearr[0]);
    unset($freearr);
    $free = trim($freeA[count($freeA)-1]);
    unset($freeA);
    return $free;
}

$opts = \getopt("d::f::");
$isCleanRun = isset($opts["f"]);
define('DEBUG', isset($opts["d"]));

require __DIR__ . '/common.php';
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;

if (\PHP_SAPI !== 'cli') {
    die('CLI-only');
}

$tstart = \microtime(true);
$done = false;

function mem_check_job()
{
    if(get_server_memory_free()<mem_max_diff)
    {
        $done = true;
        die("MAX MEMORY THRESHOLD REACHED, ABORT!!!");
    }
}

$logger = new Logger('merge');
$logger->pushHandler($loggingStream);

$targetServer = $svdb['target'];
$targetDbPlayer = $dbconf['target']["player_db"];
$targetDbAccount = $dbconf['target']["account_db"];
$servers = [];
if (isset($svdb["sourceA"])) {
    $servers[] = "sourceA";
}

if (isset($svdb["sourceB"])) {
    $servers[] = "sourceB";
}

if ($isCleanRun) {
    $logger->info("Reinitializing...");
    $baseSQL = \file_get_contents(__DIR__ ."/base.sql");
    $output = \shell_exec("mysql -u" . $dbconf['target']['user'] . " -p" . $dbconf['target']['pass'] . " -h " . $dbconf['target']['host'] . " -D $targetDbPlayer < " . __DIR__ . "/base.sql");
    $logger->info("\tMysql output (empty expected): " . $output);
    unlink(__DIR__."/logs/main.log");
    $logger->info("Reinitialized!");
}
else {
    $logger->warn("Dirty run");
}

// TODO: load all relevant data and resolve conflicts first

$charNameConflicts = [];
foreach ($servers as $svname) {
    $sourceServer = $svdb[$svname];
    $playerDbName = $dbconf[$svname]['player_db'];
    $accountDbName = $dbconf[$svname]['account_db'];

    $playerSt = $sourceServer->run("SELECT name, playtime FROM $playerDbName.player");

    while ($player = $playerSt->fetch(\PDO::FETCH_ASSOC)) {
        $nm = strtolower($player['name']);
        if (!isset($charNameConflicts[$nm])) {
            $charNameConflicts[$nm] = [
                'score' => [],
                'winner' => ""
            ];
        }

        $charNameConflicts[$nm]['score'][$svname] = $player['playtime'];

        foreach ($charNameConflicts[$nm]['score'] as $sv => $score) {
            if (!$charNameConflicts[$nm]['winner'] || $charNameConflicts[$nm]['score'][$charNameConflicts[$nm]['winner']] < $score) {
                $charNameConflicts[$nm]['winner'] = $sv;
            }
        }
    }
}


/*****************************************************************
 *                   CANONICAL EVENT FLAGS
 * **************************************************************/
$eventFlags = [
    "5001" =>  20,
    "amber_slot" => 1,
    "amber_target" => 5,
    "betrayal_event_active_on" => 10,
    "betrayal_event_target" => 8,
    "block_call_monsterchat" => 1,
    "bodukan_open" => 1,
    "bookworldday" => 99999,
    "book_event" => 1,
    "book_set_chance" => 50,
    "book_set_delay" => 6,
    "budokan_event" => 1,
    "budokan_open" => 0,
    "budoka_open" => 2,
    "change_itemattr_cycle" => 5,
    "christmas_spawn" => 47,
    "crimson_slot" => 6,
    "currency_exchange_enabled" => 1,
    "disable_movspeed_log" => 1,
    "dragontemple_channel" => 2,
    "dragontemple_guild" => 8131,
    "dragontemple_last_defeat" => 1539323770,
    "dragontemple_open" => 1,
    "dragontemple_password" => 1506,
    "dragontemple_require_password" =>  1,
    "dragontemple_start" => 1539321977,
    "dragontemple_starting_run_guild" => 8131,
    "ds_drop" =>  212,
    "ed_record_time" => 139,
    "egg_hunt_respawndelay" => 30,
    "enabled_chat_mitigation" => 1,
    "eventarena_maxlv" => 105,
    "eventarena_minlv" => 30,
    "eventarena_open2" => 1,
    "eventarena_type" => 1,
    "event_drop" => 1,
    "fish_miss_pct" => 20,
    "foxhunt_mapindex" => 103,
    "frozen_nation_war_enabled" => 1,
    "frozen_threeway" => 1,
    "frozen_threeway_war" => 1,
    "gold_drop_limit_time" => 30000,
    "gold_trade_cycle" => 365,
    "gold_trade_enable" => 1,
    "halloween_activity_required" => 200,
    "halloween_candy_box_cost" => 2,
    "halloween_chance" => 200,
    "halloween_drop_decay" => 100,
    "halloween_golden_rate" => 4,
    "halloween_rankc_4" => 80,
    "halloween_rankc_5" => 100,
    "halloween_reward_level1" => 1,
    "halloween_reward_level2" => 2,
    "halloween_reward_level3" => 3,
    "hivalue_item_sell" => 1,
    "horse_skill_book_drop" => 5000,
    "interest_e1" => 10,
    "interest_e2" => 10,
    "interest_e3" => 10,
    "king_vid" => 922143,
    "lotto_round" => 2,
    "mob_dam" => 110,
    "mob_exp" => 400,
    "mob_exp_buyer" => 400,
    "mob_gold" => 500,
    "mob_gold_buyer" => 500,
    "mob_gold_pct" => 100,
    "mob_gold_pct_buyer" => 100,
    "mob_item" => 120,
    "mob_item_buyer" => 150,
    "MonarchHealGold" => 250000,
    "monarch_election" => 2,
    "monsterlog_enabled" => 1,
    "naaga_story_enabled" => 1,
    "nemere_lair_enabled" => 1,
    "nemere_rewards_enabled" => 1,
    "open_budokan" => 1,
    "pvp_arena_enabled" => 1,
    "pvp_damage_reduce" => 78,
    "pvp_maxlevel" => 105,
    "pvp_minlevel" => 97,
    "pvp_price_count" => 1,
    "pvp_price_vnum" => 50042,
    "pvp_round_gold" => 2000000,
    "pvp_tournament" => 1538851287,
    "quiver_crafting_enabled" => 1,
    "remain_egg" => 9,
    "remain_egg1" => 9,
    "remain_egg2" => 9,
    "remain_egg4" => 9,
    "spawn_santa" => 1,
    "spider_dungeon_level" => 30,
    "spring_map" => 1,
    "summer_18_thow_hours" => 24,
    "summer_18_throw_hours" => 24,
    "threeway_frozen" => 1,
    "threeway_passage_open_2" => 1,
    "threeway_war_boss_count" => 5,
    "threeway_war_dead_count" => 50,
    "threeway_war_kill_count" => 250,
    "three_skill_item" => 100,
    "tome_set_chance" => 25,
    "tome_set_delay" => 6,
    "treasure_system_enabled" => 1,
    "tree_skill_item" => 75,
    "t_lottery_num" => 32,
    "t_lottery_vault" => 302945,
    "wolrdbookday" => 1,
    "xmas_lottery_chance" => 1000,
    "xmas_lottery_id" => 1,
    "xmas_lottery_stage" => 2,
    "xmas_present_drop_rate" => 1250,
    "xmas_self_gifting_item" => 1
];
/*
$eventFlags = [
    "mob_gold" => 500,
    "book_set_chance" => 50,
    "tome_set_chance" => 25,
    "mob_exp" => 400,
    "mob_item" => 120,
    "mob_gold_buyer" => 500,
    "interest_e1" => 10,
    "book_set_delay" => 6,
    "hivalue_item_sell" => 1,
    "mob_item_buyer" => 120,
    "tome_set_delay" => 6,
    "mob_exp_buyer" => 400,
    "eventarena_minlv" => 30,
    "eventarena_maxlv" => 105,
    "eventarena_open3" => 1,
    "interest_e2" => 10,
    "interest_e3" => 10,
    "three_skill_item" => 100,
    "pvp_round_gold" => 1000000,
    "pvp_price_count" => 1,
    "pvp_price_vnum" => 50042,
    "pvp_minlevel" => 80,
    "pvp_maxlevel" => 105,
    "pvp_tournament" => 1501349010,
    "remain_egg" => 9,
    "king_vid" => 23820734,
    "mob_dam" => 110,
    "fish_miss_pct" => 20,
    "horse_skill_book_drop" => 5000,
    "threeway_war_dead_count" => 50,
    "threeway_war_kill_count" => 250,
    "threeway_war_boss_count" => 5,
    "MonarchHealGold" => 250000,
    "dragontemple_open" => 1,
    "dragontemple_password" => 4,
    "t_lottery_num" => 5,
    "t_lottery_vault" => 500000000,
    "mob_gold_pct" => 100,
    "mob_gold_pct_buyer" => 100,
    "halloween_chance" => 200,
    "budokan_event" => 1,
    "drop_moon" => 1,
    "wolrdbookday" => 1,
    "gold_trade_enable" => 1,
    "gold_trade_cycle" => 365,
    "book_event" => 1,
    "event_drop" => 1,
    "christmas_spawn" => 47,
    "budoka_open" => 2,
    "ds_drop" => 250,
    "egg_hunt_respawndelay" => 30,
    "change_itemattr_cycle" => 5,
    "threeway_passage_open_1" => 1,
    "threeway_passage_open_2" => 1,
    "threeway_passage_open_3" => 1,
    "disable_movspeed_log" => 1,
    "foxhunt_mapindex" => 103,
    "frozen_threeway_war" => 1,
    "threeway_frozen" => 1,
    "frozen_threeway" => 1,
    "gold_drop_limit_time" => 30000,
    "block_call_monsterchat" => 1,
    "bookworldday" => 1,
    "monarch_election" => 2,
    "halloween_drop_decay" => 200,
    "halloween_candy_box_cost" => 2,
    "halloween_golden_rate" => 4,
    "halloween_activity_required" => 200,
    "halloween_reward_level2" => 2,
    "halloween_reward_level1" => 1,
    "halloween_reward_level3" => 3,
    "halloween_trickortreat_enabled" => 1,
    "pvp_damage_reduce" => 78,
    "bodukan_open" => 1,
    "betrayal_event_active_on" => 8,
    "betrayal_event_target" => 6,
    "eventarena_type" => 1,
    "halloween_rankc_4" => 80,
    "halloween_rankc_5" => 100,
    "tree_skill_item" => 75,
    "nemere_rewards_enabled" => 1,
    "crimson_slot" => 6,
    "amber_slot" => 1,
    "amber_target" => 5,
    "nemere_lair_enabled" => 1
];*/

foreach ($eventFlags as $flag => $value) {
    $logger->info("Inserted evflag", ["flag" => $flag, "value" => $value]);
    $targetServer->run("INSERT INTO $targetDbPlayer.quest (szName, lValue) VALUES ('" . $flag . "', $value)");
}

$logger->info("Evflags inserted");
$logger->info("Starting merge...");
/**
 * MERGE SERVERS ONE BY ONE
 */
$lastAccountID = $lastPlayerID = 1;
$lastGuildID = $lastItemID = 0;
foreach ($servers as $svname) {
    echo "Starting with server " . $svname. "\n";
    $logger->info("Mergin at server " . $svname);
    $prettyServerName = $dbconf[$svname]['pretty_name'];
    $playerDbName = $dbconf[$svname]['player_db'];
    $accountDbName = $dbconf[$svname]['account_db'];
    $sourceServer = $svdb[$svname];

    // Initialize logging stream (specific loggers are created on demand)
    $dateFormat = "d/M, H:i:s e";
    $output = "%datetime% > %channel%.%level_name% > [" . $prettyServerName . "] %message% %context%\n";
    $loggingStream->setFormatter(new LineFormatter($output, $dateFormat));

    $oldToNew['account-id'] = [];
    $oldToNew['account-login'] = [];
    $accountOldByCase = [];
    $oldToNew['player-id'] = [];
    $oldToNew['player-name'] = [];
    $oldToNew['item-id'] = [];
    $oldToNew['guild-id'] = [];
    $oldToNew['guild-name'] = [];

    $accountByPID = [];
    $accountNameByAID = [];
    $masterByGuild = [];

    /*****************************************************************
     *                      ACCOUNT REASSIGN
     * **************************************************************/
    $logger->info("Reassigning accounts...");
    $accountSt = $sourceServer->run("SELECT * FROM $accountDbName.account ORDER BY id ASC");
    while ($a = $accountSt->fetch(\PDO::FETCH_ASSOC)) {
        if($safemode)
            mem_check_job();
        $nAccID = ++$lastAccountID;

        // Random string of digits + id
        $char_set = "abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $nAccLogin = "";
        $max = strlen($char_set);

        $length = 24 - strlen("" . $nAccID);
        while ($length--) {
            $nAccLogin .= $char_set[mt_rand(0, $max - 1)];
        }

        $nAccLogin .= "-" . $nAccID;

        $oldToNew['account-id'][$a['id']] = $nAccID;
        $oldToNew['account-login'][$a['login']] = $nAccLogin;
        $accountOldByCase[strtolower($a['login'])] = $a['login'];
        $logger->info(\sprintf("u#%d [%s] --> u#%d [%s]", $a['id'], $a['login'], $nAccID, $nAccLogin));

        $a['cookie_id'] = ''; // Clear cookie id
        $a['activation_id'] = $prettyServerName . "-" . $a['login']; // Save the original account name
        $a['status'] = $a['status'] == 'OK' ? 'OK' /* MIGRATE */ : $a['status']; // Set migrate status
        $a['admin'] = $a['admin'] == '0' ? '0' : '1'; //default to 0 if null
        $a['last_post'] = $a['last_post'] == '' ? '0000-00-00 00:00:00' : $a['last_post']; //insert empty date if null
        $a['last_pw_change'] = $a['last_pw_change'] == '' ? '0000-00-00 00:00:00' : $a['last_pw_change']; //insert empty date if null
        $a['voted'] = $a['voted'] == '' ? '0' : $a['voted']; //insert 0 if null
        $a['voted_at'] = $a['voted_at'] == '' ? '0000-00-00 00:00:00' : $a['voted_at']; //insert empty date if null
        $a['vote_registered_at'] = $a['vote_registered_at'] == '' ? '0000-00-00 00:00:00' : $a['vote_registered_at']; //insert empty date if null
        $a['last_login'] = $a['last_login'] == '' ? '0000-00-00 00:00:00' : $a['last_login']; //insert empty date if null
        $a['birthDay'] = $a['birthDay'] == '' ? '0000-00-00 00:00:00' : $a['birthDay']; //insert empty date if null
        $a['last_failed_attempt'] = $a['last_failed_attempt'] == '' ? '0000-00-00 00:00:00' : $a['last_failed_attempt']; //insert empty date if null
        $a['last_play'] = $a['last_play'] == '' ? '0000-00-00 00:00:00' : $a['last_play']; //insert empty date if null
        $a['last_activation_request'] = $a['last_activation_request'] == '' ? '0000-00-00 00:00:00' : $a['last_activation_request']; //insert empty date if null
        $a['last_forum_name_change'] = $a['last_forum_name_change'] == '' ? '0000-00-00 00:00:00' : $a['last_forum_name_change']; //insert empty date if null
        $a['id'] = $nAccID;
        $a['login'] = $nAccLogin;

        $accountNameByAID[$nAccID] = $nAccLogin;
        $accounts[] = $a;
    }
    $logger->info("Inserting accounts...");
    $accountSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbAccount,
        "account",
        $accounts,
        [
            "id",
            "login",
            "password",
            "using_sentry",
            "social_id",
            "forum_name",
            "activation_id",
            "cookie_id",
            "user_title",
            "create_time",
            "forum_posts",
            "last_post",
            "admin",
            "team_member",
            "origin",
            "is_testor",
            "status",
            "securitycode",
            "newsletter",
            "empire",
            "name_checked",
            "availDt",
            "mileage",
            "cash",
            "gold_expire",
            "silver_expire",
            "safebox_expire",
            "autoloot_expire",
            "fish_mind_expire",
            "marriage_fast_expire",
            "money_drop_rate_expire",
            "shop_double_up_expire",
            "total_cash",
            "total_mileage",
            "channel_company",
            "ip",
            "lang",
            "last_pw_change",
            "referrer",
            "referral_level",
            "voted",
            "voted_at",
            "vote_registered_at",
            "unsubscribe",
            "playtime",
            "last_login",
            "birthDay",
            "failures",
            "last_failed_attempt",
            "last_play",
            "last_forum_name_change",
            "last_activation_request",
            "email_lock"
        ]
    );
    unset($accounts); // Free up the memory

    $logger->info("Accounts copied", ["count" => $lastAccountID]);

    /*****************************************************************
     *                      SENTRY REASSIGN
     * **************************************************************/
    $sentrySt = $sourceServer->run("SELECT * FROM $accountDbName.sentry");
    $sentries = [];
    while ($s = $sentrySt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['account-id'][$s['account_id']])) {
            $logger->warn(sprintf("Account %d doesn't exist (sentry id %d)!", $s['account_id'], $s['sentry_id']));
            continue;
        }

        if($safemode)
            mem_check_job();

        $s['account_id'] = $oldToNew['account-id'][$s['account_id']]; // Clear cookie id
        $s['last_use'] = $s['last_use'] == '' ? '0000-00-00 00:00:00' : $s['last_use'];
        $s['validation_time'] = $s['validation_time'] == '' ? '0000-00-00 00:00:00' : $s['validation_time'];
        $s['creation_time'] = $s['creation_time'] == '' ? '0000-00-00 00:00:00' : $s['creation_time'];
        $sentries[] = $s;
    }
    $sentrySt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbAccount,
        "sentry",
        $sentries,
        [
            "sentry_id",
            "account_id",
            "activation_code",
            "creation_time",
            "validation_time",
            "last_use",
            "type"
        ]
    );
    $logger->info("Sentry copied", ["count" => count($sentries)]);
    unset($sentries); // Free up the memory


    /*****************************************************************
     *                      EMAIL REASSIGN
     * **************************************************************/
    $emailSt = $sourceServer->run("SELECT * FROM $accountDbName.email");
    $emails = [];
    while ($e = $emailSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['account-id'][$e['account_id']])) {
            $logger->warn(sprintf("Account %d doesn't exist (email id %d)!", $e['account_id'], $e['email_id']));
            continue;
        }

        if($safemode)
            mem_check_job();

        $e['account_id'] = $oldToNew['account-id'][$e['account_id']];
        $emails[] = $e;
    }
    $emailSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbAccount,
        "email",
        $emails,
        [
            "account_id",
            "email",
            "status",
            "activation_code",
            "request_confirmation_date",
            "confirmation_date",
            "last_change"
        ]
    );
    $logger->info("Emails copied", ["count" => count($emails)]);
    unset($emails); // Free up the memory


    /*****************************************************************
     *                      PLAYER REASSIGN
     * **************************************************************/
    $playerSt = $sourceServer->run("SELECT *, HEX(skill_level) as skill_level, HEX(quickslot) as quickslot FROM $playerDbName.player ORDER BY id ASC");
    $players = [];
    while ($p = $playerSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['account-id'][$p['account_id']])) {
            $logger->warning("Owning account doesn't exist!", ["pid" => $p['id'], "aid" => $p['account_id']]);
            continue;
        }

        if($safemode)
            mem_check_job();

        $nPlayerID = ++$lastPlayerID;

        if (!isset($charNameConflicts[strtolower($p['name'])])) {
            $logger->error("Player name doesn't exist??", ["name" => $p['name']]);
            die;
        }

        if (!$charNameConflicts[strtolower($p['name'])]['winner']) {
            $logger->error("Player name conflict has no winner?", ["name" => $p['name'], "data" => $charNameConflicts[strtolower($p['name'])]]);
            die;
        }

        $nInfo = $charNameConflicts[strtolower($p['name'])];
        $nPlayerName = $nInfo['winner'] == $svname ? $p['name'] : "";
        $conflictLevel = "NONE";
        if (!$nPlayerName) {
            $potentialName = ucfirst($prettyServerName) . $p['name'];
            if (strlen($potentialName) <= 24 && !isset($charNameConflicts[strtolower($potentialName)])) {
                $nPlayerName = $potentialName;
                $conflictLevel = "POSTFIX";
            }
            else {
                $potentialName = $p['name'] . ucfirst($prettyServerName);
                if (strlen($potentialName) <= 24 && !isset($charNameConflicts[strtolower($potentialName)])) {
                    $nPlayerName = $potentialName;
                    $conflictLevel = "PREFIX";
                }
                else {
                    $potentialName = $p['name'];
                    $i = 0;
                    while ($nPlayerName == "" && strlen($potentialName) < 24) {
                        $potentialName = ($i % 2) ? "x" . $potentialName : $potentialName . "x";
                        if (!isset($charNameConflicts[strtolower($potentialName)])) {
                            $nPlayerName = $potentialName;
                            break;
                        }
                        ++$i;
                    }

                    if ($nPlayerName == "") {
                        // Still no name! Woah! Assign a random one
                        $nameList = ["Dulbel", "Pulfrost", "Ditra", "Wrymbel", "Cy", "Rear", "Cyso", "Sinskynix", "Redta", "Nus", "Rum", "Takaz", "Mankin", "Splenle", "Tic", "Bloodyaz", "Corre", "Draysuhn", "Reusvalta", "Zallsi", "Rema", "Laiblue", "Dudie", "Raslean", "Nebolt", "Cortian", "Raiter", "Derthrack", "Tathe", "Rasha", "Riasoot", "Lantlho", "Ballin", "Jatra", "Vendusk", "Darfu", "Potyrnian", "Giathra", "Komor", "Tyrfyex", "Noctra", "Sase", "Xeslier", "Phyfy", "Gloomsto", "Re", "Nuswhi", "Keri", "Soulsud", "Cirsto", "Fusidor", "Myckme", "Larkrall", "Nenar", "Crypo", "Rafi", "Duskniuswyrm", "Rixsian", "Riandi", "Thunra", "Redow", "Trade", "Wahorn", "Kahhrire", "Iho", "Santhny", "Thrama", "Trakkan", "Ardaz", "Khella", "Sudras", "Lec", "Larke", "Kellier", "Belsi", "Ryrath", "Tharri", "Sheesin", "Ryves", "Moshim", "Narnax", "Fule", "Clevha", "Shapo", "Legar", "Bloodmonra", "Ranbon", "Zurlho", "Dede", "Be-zur", "Klanggvi", "Holgen", "Smeltmond", "Sheyes", "Razall", "Tic", "Moonven", "Kary", "Drayra", "Tan", "Starrze", "Ralin", "Beadarro", "Aunar", "Rummnetia", "Tenrall", "Lan", "Gabe", "Arion", "Lixfi", "Chocir", "Gadu", "Khel-thra", "Hocym"];
                        $max = count($nameList);
                        $potentialName = "";
                        while (!$potentialName || isset($charNameConflicts[strtolower($potentialName)])) {
                            $potentialName = $nameList[mt_rand(0, $max - 1)];
                        }

                        if (!isset($charNameConflicts[strtolower($potentialName)])) {
                            $nPlayerName = $potentialName;

                            $conflictLevel = "LIST";
                        }
                        else {
                            // Fuck it random it is
                            $char_set = "abcdefghijklmnopqrstuvwxyz1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZ";
                            $nPlayerName = "";
                            $max = strlen($char_set);
                            $length = 16;
                            while ($length--) {
                                $nPlayerName .= $char_set[mt_rand(0, $max - 1)];
                            }

                            $conflictLevel = "RANDOM";
                        }
                    }
                    else {
                        $conflictLevel = "XXX";
                    }
                }
            }
        }

        $oldToNew['player-id'][$p['id']] = $nPlayerID;
        $oldToNew['player-name'][$p['name']] = $nPlayerName;
        $logger->info(\sprintf("p#%d [%s] --> p#%d [%s] (Conflict: %s)", $p['id'], $p['name'], $nPlayerID, $nPlayerName, $conflictLevel));

        $p['id'] = $nPlayerID;
        $p['name'] = $nPlayerName;
        $p['account_id'] = $oldToNew['account-id'][$p['account_id']];

        if ($conflictLevel != "NONE") {
            $p['change_name'] = 1;
        }

        $players[] = $p;
    }
    $playerSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "player",
        $players,
        [
            "id",
            "account_id",
            "name",
            "job",
            "voice",
            "dir",
            "x",
            "y",
            "z",
            "map_index",
            "exit_x",
            "exit_y",
            "exit_map_index",
            "hp",
            "mp",
            "stamina",
            "random_hp",
            "random_sp",
            "playtime",
            "level",
            "level_step",
            "st",
            "ht",
            "dx",
            "iq",
            "exp",
            "gold",
            "stat_point",
            "skill_point",
            "quickslot",
            "ip",
            "part_main",
            "part_base",
            "part_hair",
            "skill_group",
            "skill_level",
            "alignment",
            "last_play",
            "change_name",
            "sub_skill_point",
            "stat_reset_count",
            "horse_hp",
            "horse_stamina",
            "horse_level",
            "horse_hp_droptime",
            "horse_riding",
            "horse_skill_point",
            "creation_time",
            "mall_key",
            "inventory_space",
            "achievement_points",
            "achievement_ranks"
        ],
        [
            "skill_level" => true,
            "quickslot" => true
        ]
    );
    unset($players); // Free up the memory

    $logger->info("Players copied", ["count" => $lastPlayerID]);


    /*****************************************************************
     *                 PLAYER INDEX GENERATION
     * **************************************************************/

    $pindexSt = $sourceServer->run("SELECT * FROM $playerDbName.player_index ORDER BY id ASC");
    $pindex = [];
    while ($pidx = $pindexSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['account-id'][$pidx['id']])) {
            $logger->warn("Index owning account doesn't exist!", ["idx" => $pidx]);
            continue;
        }

        if($safemode)
            mem_check_job();

        $pidx['id'] = $oldToNew['account-id'][$pidx['id']];
        for ($i = 1; $i <= 4; ++$i) {
            if ($pidx['pid' . $i] == 0) {
                continue;
            }

            if (!isset($oldToNew['player-id'][$pidx['pid' . $i]])) {
                $logger->warn("Index-referenced player character doesn't exist!", ["idx" => $pidx['pid' . $i]]);
                continue;
            }

            $pidx['pid' . $i] = $oldToNew['player-id'][$pidx['pid' . $i]];
            $accountByPID[$pidx['pid' . $i]] = $pidx['id'];
        }

        $logger->debug(\sprintf("Creating index for u#%d (dynasty %d)", $pidx['id'], $pidx['dynasty']));
        $pindex[] = $pidx;
    }
    $pindexSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "player_index",
        $pindex,
        [
            "id",
            "pid1",
            "pid2",
            "pid3",
            "pid4",
            "dynasty"
        ],
        [],
        10000
    );
    unset($pindex); // Free up the memory

    $logger->info("Indexes created");

    /*****************************************************************
     *                      GUILD RECREATION
     * **************************************************************/
    $guildSt = $sourceServer->run("SELECT *, HEX(skill) as skill FROM $playerDbName.guild ORDER BY id ASC");
    $guilds = [];
    while ($g = $guildSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['player-id'][$g['master']])) {
            $logger->warn("Guild master pid doesn't exist!", $g);
            continue;
        }

        if($safemode)
            mem_check_job();

        $nGuildID = ++$lastGuildID;
        $nGuildName = "[" . ucfirst($prettyServerName) . "] " . $g['name'];
        $nGuildMaster = $oldToNew['player-id'][$g['master']];

        $oldToNew['guild-id'][$g['id']] = $nGuildID;
        $oldToNew['guild-name'][$g['name']] = $nGuildName;
        $logger->info(\sprintf("guild#%d [%s] <%d> --> guild#%d [%s] <%d>", $g['id'], $g['name'], $g['master'], $nGuildID, $nGuildName, $nGuildMaster));

        $g['id'] = $nGuildID;
        $g['name'] = $nGuildName;
        $g['master'] = $nGuildMaster;

        $masterByGuild[$nGuildID] = $nGuildMaster;
        $guilds[] = $g;
    }
    $guildSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "guild",
        $guilds,
        [
            "id",
            "name",
            "sp",
            "master",
            "level",
            "exp",
            "skill_point",
            "skill",
            "win",
            "draw",
            "loss",
            "ladder_point",
            "gold"
        ],
        [
            "skill" => true
        ]
    );
    unset($guilds); // Free up the memory

    $logger->info("Guilds copied", ["count" => $lastGuildID]);

    /*****************************************************************
     *               GUILD SECTIONS COPY W/ REASSIGN
     * **************************************************************/

    // COMMENTS
    $guildCommentsSt = $sourceServer->run("SELECT * FROM $playerDbName.guild_comment ORDER BY id ASC");
    $guildComments = [];
    while ($c = $guildCommentsSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['guild-id'][$c['guild_id']])) {
            $logger->warn("The guild associated with the comment doesn't exist!", $c);
            continue;
        }

        if($safemode)
            mem_check_job();

        $nGuildID = $oldToNew['guild-id'][$c['guild_id']];
        $logger->debug(\sprintf("Comment #%d @ g#%d -> g#%d", $c['id'], $c['guild_id'], $nGuildID));

        unset($c['id']); // let the system recreate it
        $c['guild_id'] = $nGuildID;

        $guildComments[] = $c;
    }
    $guildCommentsSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "guild_comment",
        $guildComments,
        [
            "guild_id",
            "name",
            "notice",
            "content",
            "time"
        ]
    );

    $logger->info("Guilds comments copied", ["count" => count($guildComments)]);
    unset($guildComments); // Free up the memory

    // GRADE
    $guildGradeSt = $sourceServer->run("SELECT * FROM $playerDbName.guild_grade ORDER BY guild_id ASC");
    $guildGrades = [];
    while ($gg = $guildGradeSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['guild-id'][$gg['guild_id']])) {
            $logger->warn("The guild associated with the grade doesn't exist!", $gg);
            continue;
        }

        $nGuildID = $oldToNew['guild-id'][$gg['guild_id']];
        $logger->debug(\sprintf("Guild grade %d of g#%d -> g#%d", $gg['grade'], $gg['guild_id'], $nGuildID));

        $gg['guild_id'] = $nGuildID;
        $guildGrades[] = $gg;
    }
    $guildGradeSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "guild_grade",
        $guildGrades,
        [
            "guild_id",
            "grade",
            "name",
            "auth"
        ]
    );

    $logger->info("Guilds grades copied", ["count" => count($guildGrades)]);
    unset($guildGrades); // Free up the memory

    // Member
    $guildMemberSt = $sourceServer->run("SELECT * FROM $playerDbName.guild_member");
    $guildMembers = [];
    while ($m = $guildMemberSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['guild-id'][$m['guild_id']])) {
            $logger->warn("The guild associated with the grade doesn't exist!", $m);
            continue;
        }

        if (!isset($oldToNew['player-id'][$m['pid']])) {
            $logger->warn("Guild - the player does not exist!", $m);
            continue;
        }
        $nGuildID = $oldToNew['guild-id'][$m['guild_id']];
        $nPID = $oldToNew['player-id'][$m['pid']];
        $logger->info(\sprintf("Guild combo (g#%d, p#%d) -> (g#%d, p#%d)", $m['guild_id'], $m['pid'], $nGuildID, $nPID));

        $m['guild_id'] = $nGuildID;
        $m['pid'] = $nPID;
        $guildMembers[] = $m;
    }
    $guildMemberSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "guild_member",
        $guildMembers,
        [
            "pid",
            "guild_id",
            "grade",
            "is_general",
            "offer"
        ]
    );

    $logger->info("Guilds members copied", ["count" => count($guildMembers)]);
    unset($guildMembers); // Free up the memory


    /*****************************************************************
     *               MARRIAGE COPY W/ REASSIGN
     * **************************************************************/
    $marriageSt = $sourceServer->run("SELECT * FROM $playerDbName.marriage");
    $marriages = [];
    while ($mg = $marriageSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['player-id'][$mg['pid1']]) || !isset($oldToNew['player-id'][$mg['pid2']])) {
            $logger->warn("A player associated with the marriage doesn't exist!", $mg);
            continue;
        }

        if($safemode)
            mem_check_job();

        $nPID1 = $oldToNew['player-id'][$mg['pid1']];
        $nPID2 = $oldToNew['player-id'][$mg['pid2']];
        $logger->debug(\sprintf("marriage (p#%d, p#%d) -> (p#%d, p#%d)", $mg['pid1'], $mg['pid2'], $nPID1, $nPID2));

        $mg['pid1'] = $nPID1;
        $mg['pid2'] = $nPID2;
        $marriages[] = $mg;
    }
    $marriageSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "marriage",
        $marriages,
        [
            "is_married",
            "pid1",
            "pid2",
            "love_point",
            "time"
        ]
    );

    $logger->info("Marriage copied", ["count" => count($marriages)]);
    unset($marriages); // Free up the memory


    /*****************************************************************
     *               PLAYER SECTIONS COPY W/ REASSIGN
     * **************************************************************/

    // ACHIEVEMENTS
    $achievementSt = $sourceServer->run("SELECT * FROM $playerDbName.achievement");
    $achievements = [];
    while ($ach = $achievementSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['player-id'][$ach['player_id']])) {
            $logger->warn("The player associated with the achievement doesn't exist!", $ach);
            continue;
        }

        if($safemode)
            mem_check_job();

        $nPlayerID = $oldToNew['player-id'][$ach['player_id']];
        $logger->debug(\sprintf("Achievement (%d, %d)  p#%d -> p#%d", $ach['cindex'], $ach['value'], $ach['player_id'], $nPlayerID));

        $ach['player_id'] = $nPlayerID;
        $achievements[] = $ach;
    }
    $achievementSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "achievement",
        $achievements,
        [
            "player_id",
            "cindex",
            "value"
        ]
    );

    $logger->info("Achievements copied", ["count" => count($achievements)]);
    unset($achievements); // Free up the memory

    // ACTIVITY
    $activitySt = $sourceServer->run("SELECT * FROM $playerDbName.activity");
    $activity = [];
    while ($actv = $activitySt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['player-id'][$actv['pid']])) {
            $logger->warn("The player associated with the activity doesn't exist!", $actv);
            continue;
        }

        $nPlayerID = $oldToNew['player-id'][$actv['pid']];
        $logger->debug(\sprintf("Activity p#%d -> p#%d", $actv['pid'], $nPlayerID));

        $actv['pid'] = $nPlayerID;
        $activity[] = $actv;
    }
    $activitySt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "activity",
        $activity,
        [
            "pid",
            "today_pvp",
            "today_pve",
            "today_other",
            "today_gk",
            "total",
            "last_update"
        ]
    );

    $logger->info("activity copied", ["count" => count($activity)]);
    unset($activity); // Free up the memory

    // AFFECT
    $affectSt = $sourceServer->run("SELECT * FROM $playerDbName.affect");
    $affects = [];
    while ($aff = $affectSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['player-id'][$aff['dwPID']])) {
            $logger->warn("The player associated with the affect doesn't exist!", $aff);
            continue;
        }

        if($safemode)
            mem_check_job();

        $nPlayerID = $oldToNew['player-id'][$aff['dwPID']];
        $logger->debug(\sprintf("Affect p#%d -> p#%d (%d, %d, %d)", $aff['dwPID'], $nPlayerID, $aff['bType'], $aff['bApplyOn'], $aff['lApplyValue']));

        $aff['dwPID'] = $nPlayerID;
        $affects[] = $aff;
    }
    $affectSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "affect",
        $affects,
        [
            "dwPID",
            "bType",
            "bApplyOn",
            "lApplyValue",
            "dwFlag",
            "lDuration",
            "lSPCost"
        ]
    );

    $logger->info("affect copied", ["count" => count($affects)]);
    unset($affects); // Free up the memory

    // PETS
    $petSt = $sourceServer->run("SELECT * FROM $playerDbName.pet");
    $pets = [];
    while ($pet = $petSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['player-id'][$pet['player_id']])) {
            $logger->warn("The player associated with the pet doesn't exist!", $pet);
            continue;
        }

        $nPlayerID = $oldToNew['player-id'][$pet['player_id']];
        $logger->debug(\sprintf("Pet p#%d -> p#%d", $pet['player_id'], $nPlayerID));

        $pet['player_id'] = $nPlayerID;
        $pets[] = $pet;
    }
    $petSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "pet",
        $pets,
        [
            "player_id",
            "activated",
            "name",
            "type",
            "level",
            "exp",
            "happiness",
            "loyalty",
            "aggression",
            "evasion",
            "defense",
            "last_role",
            "status",
            "slot"
        ]
    );

    $logger->info("pets copied", ["count" => count($pets)]);
    unset($pets); // Free up the memory

    /* WALLET
    $walletSt = $sourceServer->run("SELECT * FROM $playerDbName.wallet");
    $wallets = [];
    while ($wt = $walletSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['player-id'][$wt['player_id']])) {
            $logger->warn("The player associated with the wallet doesn't exist!", $wt);
            continue;
        }

        $nPlayerID = $oldToNew['player-id'][$wt['player_id']];
        $logger->debug(\sprintf("wallet p#%d -> p#%d", $wt['player_id'], $nPlayerID));

        $wt['player_id'] = $nPlayerID;
        $wallets[] = $wt;
    }
    $walletSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "wallet",
        $wallets,
        [
            "player_id",
            "balance"
        ]
    );

    $logger->info("wallet copied", ["count" => count($wallets)]);
    unset($wallets); // Free up the memory 
    
    We don't do wallets anymore :)
    */

    // QUESTS
    $questSt = $sourceServer->run("SELECT * FROM $playerDbName.quest WHERE dwPID > 0");
    $quests = [];
    while ($q = $questSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['player-id'][$q['dwPID']])) {
            $logger->warn("The player associated with the quest doesn't exist!", $q);
            continue;
        }

        if($safemode)
            mem_check_job();

        $nPlayerID = $oldToNew['player-id'][$q['dwPID']];
        $logger->debug(\sprintf("quest p#%d -> p#%d", $q['dwPID'], $nPlayerID));

        $q['dwPID'] = $nPlayerID;
        $quests[] = $q;
    }
    $questSt->closeCursor();

    $targetServer->run("ALTER TABLE $targetDbPlayer.`quest` DISABLE KEYS");
    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "quest",
        $quests,
        [
            "dwPID",
            "szName",
            "szState",
            "lValue"
        ],
        [],
        15000
    );
    $targetServer->run("ALTER TABLE $targetDbPlayer.`quest` ENABLE KEYS");

    $logger->info("Quests copied (keys enabled)", ["count" => count($quests)]);
    unset($quests); // Free up the memory


    /*****************************************************************
     *               PLAYER NAME-BASED SECTIONS COPY W/ REASSIGN
     * **************************************************************/

    // BLOCK LIST
    $blockSt = $sourceServer->run("SELECT * FROM $playerDbName.block_list");
    $blist = [];
    while ($b = $blockSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['player-name'][$b['me']]) || !isset($oldToNew['player-name'][$b['blocked']])) {
            $logger->warn("One player associated with the blocklist doesn't exist!", $b);
            continue;
        }

        $nMe = $oldToNew['player-name'][$b['me']];
        $nYou = $oldToNew['player-name'][$b['blocked']];
        $logger->debug(\sprintf("Blocklist (%s, %s) -> (%s, %s)", $b['me'], $b['blocked'], $nMe, $nYou));

        $b['me'] = $nMe;
        $b['blocked'] = $nYou;
        $blist[] = $b;
    }
    $blockSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "block_list",
        $blist,
        [
            "me",
            "blocked",
            "both"
        ]
    );

    $logger->info("Blocklist copied", ["count" => count($blist)]);
    unset($blist); // Free up the memory

    // MESSENGER LIST (Friends)
    $friendSt = $sourceServer->run("SELECT * FROM $playerDbName.messenger_list");
    $friendList = [];
    while ($friend = $friendSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['player-name'][$friend['account']]) || !isset($oldToNew['player-name'][$friend['companion']])) {
            $logger->warn("One player associated with the friendlist (messenger_list) doesn't exist! Skipping friends", $friend);
            continue;
        }

        $nMe = $oldToNew['player-name'][$friend['account']];
        $nYou = $oldToNew['player-name'][$friend['companion']];
        $logger->info(\sprintf("Friendlist (%s, %s) -> (%s, %s)", $friend['account'], $friend['companion'], $nMe, $nYou));

        $friend['account'] = $nMe;
        $friend['companion'] = $nYou;
        $friendList[] = $friend;
    }
    $friendSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "messenger_list",
        $friendList,
        [
            "account",
            "companion"
        ]
    );

    $logger->info("Friends copied", ["count" => count($friendList)]);
    unset($friendList); // Free up the memory

    // PRIVATE SHOP
    $logger->info("Loading private shops!");
    $pshopSt = $sourceServer->run("SELECT DISTINCT p.* FROM $playerDbName.private_shop as p RIGHT JOIN $playerDbName.private_shop_items ON private_shop_items.pid = p.pid WHERE channel > 0");
    $shops = [];
    while ($shop = $pshopSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['player-id'][$shop['pid']])) {
            $logger->warn("The player associated with the private shop doesn't exist!", $shop);
            continue;
        }

        $nPlayerID = $oldToNew['player-id'][$shop['pid']];
        $logger->debug(\sprintf("shop p#%d -> p#%d", $shop['pid'], $nPlayerID));

        $shop['pid'] = $nPlayerID;
        $shops[] = $shop;
    }
    $pshopSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "private_shop",
        $shops,
        [
            "pid",
            "x",
            "y",
            "mapindex",
            "channel",
            "sign",
            "open_time",
            "offline_left",
            "premium_left",
            "is_closed",
            "gold_stash"
        ]
    );

    $logger->info("private shops copied", ["count" => count($shops)]);
    unset($shops); // Free up the memory

    // PRIVATE SHOP ITEMS
    $pItemsSt = $sourceServer->run("SELECT * FROM $playerDbName.private_shop_items");
    $shopitems = [];
    while ($si = $pItemsSt->fetch(\PDO::FETCH_ASSOC)) {
        if (!isset($oldToNew['player-id'][$si['pid']])) {
            $logger->warn("The player associated with the shop item id doesn't exist!", $si);
            continue;
        }

        $nPlayerID = $oldToNew['player-id'][$si['pid']];
        $logger->debug(\sprintf("Shop item @ pos %d -> p#%d -> p#%d", $si['pos'], $si['pid'], $nPlayerID));

        $si['pid'] = $nPlayerID;
        $shopitems[] = $si;
    }

    $pItemsSt->closeCursor();

    doDatabaseInsert(
        $targetServer,
        $targetDbPlayer,
        "private_shop_items",
        $shopitems,
        [
            "pid",
            "pos",
            "price"
        ]
    );

    $logger->info("private shop items copied", ["count" => count($shopitems)]);
    unset($shopitems); // Free up the memory

    /*****************************************************************
     *              ITEM COPY (in bulks cus too many)
     * *************************************************************/
    echo "Copying items...\n";
    {
        $total = $sourceServer->run("SELECT COUNT(1) as result FROM $playerDbName.item")->fetch()['result'];
        $k = $total;
        $offset = 0;
        $BULK_SIZE = ITEM_BULK_SIZE;

        $targetServer->run("ALTER TABLE $targetDbPlayer.`item` DISABLE KEYS");
        while ($k > 0) {
            $itemSt = $sourceServer->run("SELECT * FROM $playerDbName.item LIMIT $offset, $BULK_SIZE");
            $offset += $BULK_SIZE;
            $k -= $BULK_SIZE;

            $items = [];
            while ($item = $itemSt->fetch(\PDO::FETCH_ASSOC)) {
                if (\in_array($item['vnum'], [91396, 50001, 50140, 91450, 91451, 91452, 91453, 41495, 41496, 45182, 45181])) {
                    continue; // Do not copy

                }

                if($safemode)
                    mem_check_job();

                $isAccountID = $item['window'] == "SAFEBOX" || $item['window'] == "MALL";

                if ($isAccountID && !isset($oldToNew['account-id'][$item['owner_id']])) {
                    $logger->warn("No account associated with this item!", $item);
                    continue;
                }
                elseif (!$isAccountID && !isset($oldToNew['player-id'][$item['owner_id']])) {
                    $logger->warn("No player associated with this item!", $item);
                    continue;
                }

                $nID = ++$lastItemID;
                $nOwnerID = !$isAccountID ? $oldToNew['player-id'][$item['owner_id']] : $oldToNew['account-id'][$item['owner_id']];
                $logger->info(\sprintf("i#%d owned by %s#%d -> i#%d, owner %s#%d ", $item['id'], $isAccountID ? "u" : "p", $item['owner_id'], $nID, $isAccountID ? "u" : "p", $nOwnerID));

                $oldToNew['item-id'][$item['id']] = $nID;
                $item['id'] = $nID;
                $item['owner_id'] = $nOwnerID;
                $items[] = $item;
            }
            $itemSt->closeCursor();

            doDatabaseInsert(
                $targetServer,
                $targetDbPlayer,
                "item",
                $items,
                [
                    "id",
                    "owner_id",
                    "window",
                    "pos",
                    "count",
                    "vnum",
                    "socket0",
                    "socket1",
                    "socket2",
                    "socket3",
                    "socket4",
                    "socket5",
                    "attrtype0",
                    "attrvalue0",
                    "attrtype1",
                    "attrvalue1",
                    "attrtype2",
                    "attrvalue2",
                    "attrtype3",
                    "attrvalue3",
                    "attrtype4",
                    "attrvalue4",
                    "attrtype5",
                    "attrvalue5",
                    "attrtype6",
                    "attrvalue6",
                ],
                [],
                10000
            );
            unset($items); // Free up the memory

        }
        $logger->info("Items copied, enabling keys", ["total" => $total, "lastID" => $lastItemID]);
        $targetServer->run("ALTER TABLE $targetDbPlayer.`item` ENABLE KEYS");
        $logger->info("Items - keys enabled");
    }

    /*****************************************************************
     *          ITEM_AWARD COPY WITH login reassignment
     * **************************************************************/
    echo "Item award...\n";
    {
        $awardSt = $sourceServer->run("SELECT * FROM $playerDbName.item_award WHERE item_id IS NULL");
        $awards = [];
        while ($aw = $awardSt->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($accountOldByCase[strtolower($aw['login'])]) || (!isset($oldToNew['account-login'][$accountOldByCase[strtolower($aw['login'])]]) && !isset($oldToNew['account-login'][$aw['login']]))) {
                $logger->error("One player associated with the award doesn't exist!", ["data" => $aw, "case" => $accountOldByCase[strtolower($aw['login'])]]);
            }

            $nLogin = strtolower($oldToNew['account-login'][$accountOldByCase[strtolower($aw['login'])]]);
            $logger->info(\sprintf("Rebind (%s, %d, %d) to login %s", $aw['login'], $aw['vnum'], $aw['count'], $nLogin));

            if($safemode)
                mem_check_job();

            $aw['login'] = $nLogin;
            $awards[] = $aw;
        }

        $awardSt->closeCursor();

        doDatabaseInsert(
            $targetServer,
            $targetDbPlayer,
            "item_award_merge",
            $awards,
            [
                "login",
                "vnum",
                "count",
                "given_time",
                "why",
                "socket0",
                "socket1",
                "socket2",
                "mall"
            ]
        );

        $logger->info("Item award copied", ["count" => count($awards)]);
        unset($awards); // Free up the memory

    }


    /*****************************************************************
     *                    GUILD LAND COMPENSATION
     * *************************************************************
    {
        $obj_proto = parse_proto(__DIR__ . "/object_proto.txt");

        $guildLandSt = $sourceServer->run("SELECT * FROM $playerDbName.land WHERE guild_id != 0");
        while ($land = $guildLandSt->fetch(\PDO::FETCH_ASSOC)) {
            if (!isset($oldToNew['guild-id'][$land['guild_id']])) {
                $logger->warn("Non existing guild owned a land. Cool", ["guild" => $land['guild_id']]);
                continue;
            }

            $gid = $oldToNew['guild-id'][$land['guild_id']];
            $goldReturn = $land['price'];
            $objectSt = $sourceServer->run("SELECT * FROM $playerDbName.object WHERE land_id = " . $land['id']);
            $matGive = [];
            while ($obj = $objectSt->fetch(\PDO::FETCH_ASSOC)) {
                $objVnum = $obj['vnum'];
                $materials = $obj_proto[$obj['vnum']][12];

                $thisMatGive = [];
                foreach (explode("/", $materials) as $material) {
                    $x = explode(",", $material);
                    $thisMatGive[$x[0]] = $x[1];
                }
                $matGive += $thisMatGive;

                $logger->info("Land object removal", ["guild" => $gid, "gold" => $obj_proto[$obj['vnum']][11], "mats" => $thisMatGive]);
                $goldReturn += $obj_proto[$obj['vnum']][11];
            }
            $objectSt->closeCursor();

            $logger->info("Guild given gold from land removal", ["guild" => $gid, "land_price" => $land['price'], "total_gold" => $goldReturn]);
            $targetServer->run("UPDATE $targetDbPlayer.guild SET gold = gold + " . $goldReturn . " WHERE id = " . $gid);

            foreach ($matGive as $matVnum => $matCount) {
                $pid = $masterByGuild[$gid];
                $aid = $accountByPID[$pid];
                $login = $accountNameByAID[$aid];

                $targetServer->run("INSERT INTO $targetDbPlayer.item_award_merge (login, vnum, count, given_time, why, mall) VALUES" .
                    "('" . strtolower($login) . "', " . $matVnum . ", " . $matCount . ", NOW(), '', 1)");

                $logger->info("Player given item from guild land undo", ["gid" => $gid, 'pid' => $pid, "aid" => $aid, "login" => $login, "vnum" => $matVnum, "count" => $matCount]);
            }
        }
        $guildLandSt->closeCursor();

        $logger->info("Guild lands deconstructed");
    }*/
}

$logger->info("MERGE DONE IN " . (floor( (microtime(true) - $tstart) * 1000) / 1000) . "s!");
$done = true;
