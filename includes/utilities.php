<?php

if (!defined('AOWOW_REVISION'))
    die('invalid access');

class Lang
{
    public static $timeUnits;
    public static $main;
    public static $account;
    public static $game;
    public static $error;

    public static $search;
    public static $profiler;

    public static $achievement;
    public static $class;
    public static $currency;
    public static $event;
    public static $item;
    public static $itemset;
    public static $maps;
    public static $npc;
    public static $pet;
    public static $quest;
    public static $skill;
    public static $spell;
    public static $title;
    public static $zone;

    public static $colon;

    public static function load($loc)
    {
        if (!file_exists('localization/locale_'.$loc.'.php'))
            die('File for localization '.strToUpper($loc).' not found.');
        else
            require 'localization/locale_'.$loc.'.php';

        foreach ($lang as $k => $v)
            self::$$k = $v;

        // *cough* .. reuse-hack
        self::$item['cat'][2] = [self::$item['cat'][2], self::$spell['weaponSubClass']];
        self::$item['cat'][2][1][14] .= ' ('.self::$item['cat'][2][0].')';
    }

    // todo: expand
    public static function getInfoBoxForFlags($flags)
    {
        $tmp = [];

        if ($flags & CUSTOM_DISABLED)
            $tmp[] = '[tooltip name=disabledHint]'.Util::jsEscape(self::$main['disabledHint']).'[/tooltip][span class=tip tooltip=disabledHint]'.Util::jsEscape(self::$main['disabled']).'[/span]';

        if ($flags & CUSTOM_SERVERSIDE)
            $tmp[] = '[tooltip name=serversideHint]'.Util::jsEscape(self::$main['serversideHint']).'[/tooltip][span class=tip tooltip=serversideHint]'.Util::jsEscape(self::$main['serverside']).'[/span]';

        if ($flags & CUSTOM_UNAVAILABLE)
            $tmp[] = self::$main['unavailable'];

        return $tmp;
    }

    public static function getLocks($lockId, $interactive = false)
    {
        $locks = [];
        $lock = DB::Aowow()->selectRow('SELECT * FROM ?_lock WHERE id = ?d', $lockId);
        if (!$lock)
            return '';

        for ($i = 1; $i <= 5; $i++)
        {
            $prop = $lock['properties'.$i];
            $rnk  = $lock['reqSkill'.$i];
            $name = '';

            if ($lock['type'.$i] == 1)                      // opened by item
            {
                $name = ItemList::getName($prop);
                if (!$name)
                    continue;

                if ($interactive)
                    $name = '<a class="q1" href="?item='.$prop.'">'.$name.'</a>';
            }
            else if ($lock['type'.$i] == 2)                 // opened by skill
            {
                if (!in_array($prop, [1, 2, 3, 4, 9, 16, 20])) // exclude unusual stuff
                    continue;

                $txt = DB::Aowow()->selectRow('SELECT * FROM ?_locktype WHERE id = ?d', $prop);         // todo (low): convert to static text
                $name = Util::localizedString($txt, 'name');
                if (!$name)
                    continue;

                if ($interactive)
                {
                    $skill = 0;
                    switch ($prop)
                    {
                        case  1: $skill = 633; break;   // Lockpicking
                        case  2: $skill = 182; break;   // Herbing
                        case  3: $skill = 186; break;   // Mining
                        case 20: $skill = 773; break;   // Scribing
                    }

                    if ($skill)
                        $name = '<a href="?skill='.$skill.'">'.$name.'</a>';
                }

                if ($rnk > 0)
                    $name .= ' ('.$rnk.')';
            }
            else
                continue;

            $locks[$lock['type'.$i] == 1 ? $i : -$i] = sprintf(Lang::$game['requires'], $name);
        }

        return $locks;
    }

    public static function getReputationLevelForPoints($pts)
    {
        if ($pts >= 41999)
            return self::$game['rep'][REP_EXALTED];
        else if ($pts >= 20999)
            return self::$game['rep'][REP_REVERED];
        else if ($pts >= 8999)
            return self::$game['rep'][REP_HONORED];
        else if ($pts >= 2999)
            return self::$game['rep'][REP_FRIENDLY];
        else /* if ($pts >= 1) */
            return self::$game['rep'][REP_NEUTRAL];
    }

    public static function getRequiredItems($class, $mask, $short = true)
    {
        if (!in_array($class, [ITEM_CLASS_MISC, ITEM_CLASS_ARMOR, ITEM_CLASS_WEAPON]))
            return '';

        // not checking weapon / armor here. It's highly unlikely that they overlap
        if ($short)
        {
            // misc - Mounts
            if ($class == ITEM_CLASS_MISC)
                return '';

            // all basic armor classes
            if ($class == ITEM_CLASS_ARMOR && ($mask & 0x1E) == 0x1E)
                return '';

            // all weapon classes
            if ($class == ITEM_CLASS_WEAPON && ($mask & 0x1DE5FF) == 0x1DE5FF)
                return '';

            foreach (Lang::$spell['subClassMasks'] as $m => $str)
                if ($mask == $m)
                    return $str;
        }

        if ($class == ITEM_CLASS_MISC)                      // yeah hardcoded.. sue me!
            return Lang::$spell['cat'][-5];

        $tmp  = [];
        $strs = Lang::$spell[$class == ITEM_CLASS_ARMOR ? 'armorSubClass' : 'weaponSubClass'];
        foreach ($strs as $k => $str)
            if ($mask & 1 << $k && $str)
                $tmp[] = $str;

        return implode(', ', $tmp);
    }

    public static function getStances($stanceMask)
    {
        $stanceMask &= 0xFC27909F;                          // clamp to available stances/forms..

        $tmp = [];
        $i   = 1;

        while ($stanceMask)
        {
            if ($stanceMask & (1 << ($i - 1)))
            {
                $tmp[] = self::$game['st'][$i];
                $stanceMask &= ~(1 << ($i - 1));
            }
            $i++;
        }

        return implode(', ', $tmp);
    }

    public static function getMagicSchools($schoolMask)
    {
        $schoolMask &= SPELL_ALL_SCHOOLS;                   // clamp to available schools..
        $tmp = [];
        $i   = 0;

        while ($schoolMask)
        {
            if ($schoolMask & (1 << $i))
            {
                $tmp[] = self::$game['sc'][$i];
                $schoolMask &= ~(1 << $i);
            }
            $i++;
        }

        return implode(', ', $tmp);
    }

    public static function getClassString($classMask)
    {
        $classMask &= CLASS_MASK_ALL;                       // clamp to available classes..

        if ($classMask == CLASS_MASK_ALL)                   // available to all classes
            return false;

        $tmp = [];
        $i   = 1;

        while ($classMask)
        {
            if ($classMask & (1 << ($i - 1)))
            {
                $tmp[] = '<a href="?class='.$i.'" class="c'.$i.'">'.self::$game['cl'][$i].'</a>';
                $classMask &= ~(1 << ($i - 1));
            }
            $i++;
        }

        return implode(', ', $tmp);
    }

    public static function getRaceString($raceMask)
    {
        $raceMask &= RACE_MASK_ALL;                         // clamp to available races..

        if ($raceMask == RACE_MASK_ALL)                     // available to all races (we don't display 'both factions')
            return false;

        $tmp  = [];
        $side = 0;
        $i    = 1;

        if (!$raceMask)
            return array('side' => SIDE_BOTH,     'name' => self::$game['ra'][0]);

        if ($raceMask == RACE_MASK_HORDE)
            return array('side' => SIDE_HORDE,    'name' => self::$game['ra'][-2]);

        if ($raceMask == RACE_MASK_ALLIANCE)
            return array('side' => SIDE_ALLIANCE, 'name' => self::$game['ra'][-1]);

        if ($raceMask & RACE_MASK_HORDE)
            $side |= SIDE_HORDE;

        if ($raceMask & RACE_MASK_ALLIANCE)
            $side |= SIDE_ALLIANCE;

        while ($raceMask)
        {
            if ($raceMask & (1 << ($i - 1)))
            {
                $tmp[] = '<a href="?race='.$i.'" class="q1">'.self::$game['ra'][$i].'</a>';
                $raceMask &= ~(1 << ($i - 1));
            }
            $i++;
        }

        return array ('side' => $side, 'name' => implode(', ', $tmp));
    }
}

class SmartyAoWoW extends Smarty
{
    private $config    = [];
    private $jsGlobals = [];

    public function __construct($config)
    {
        parent::__construct();

        $cwd = str_replace("\\", "/", getcwd());

        $this->assign('appName', $config['page']['name']);
        $this->assign('AOWOW_REVISION', AOWOW_REVISION);
        $this->config                 = $config;
        $this->template_dir           = $cwd.'/template/';
        $this->compile_dir            = $cwd.'/cache/template/';
        $this->config_dir             = $cwd.'/configs/';
        $this->cache_dir              = $cwd.'/cache/';
        $this->debugging              = $config['debug'];
        $this->left_delimiter         = '{';
        $this->right_delimiter        = '}';
        $this->caching                = false;              // Total Cache, this site does not work
        $this->_tpl_vars['page']      = array(
            'reqJS'  => [],                                 // <[string]> path to required JSFile
            'reqCSS' => [],                                 // <[string,string]> path to required CSSFile, IE condition
            'title'  => null,                               // [string] page title
            'tab'    => null,                               // [int] # of tab to highlight in the menu
            'type'   => null,                               // [int] numCode for spell, npc, object, ect
            'typeId' => null,                               // [int] entry to display
            'path'   => '[]'                                // [string] (js:array) path to preselect in the menu
        );
        $this->_tpl_vars['jsGlobals'] = [];
    }

    // using Smarty::assign would overwrite every pair and result in undefined indizes
    public function updatePageVars($pageVars)
    {
        if (!is_array($pageVars))
            return;

        foreach ($pageVars as $var => $val)
            $this->_tpl_vars['page'][$var] = $val;
    }

    public function display($tpl)
    {
        $tv = &$this->_tpl_vars;

        // fetch article & static infobox
        if ($tv['page']['type'] && $tv['page']['typeId'])
        {
            $article = DB::Aowow()->selectRow(
                'SELECT SQL_CALC_FOUND_ROWS article, quickInfo, locale FROM ?_articles WHERE type = ?d AND typeId = ?d AND locale = ?d UNION ALL '.
                'SELECT article, quickInfo, locale FROM ?_articles WHERE type = ?d AND typeId = ?d AND locale = 0 AND FOUND_ROWS() = 0',
                $tv['page']['type'], $tv['page']['typeId'], User::$localeId,
                $tv['page']['type'], $tv['page']['typeId']
            );

            if ($article)
            {
                $tv['article'] = ['text' => $article['article']];
                if (empty($tv['infobox']) && !empty($article['quickInfo']))
                    $tv['infobox'] = $article['quickInfo'];

                if ($article['locale'] != User::$localeId)
                    $tv['article']['params'] = ['prepend' => Util::jsEscape('<div class="notice-box" style="margin-right:245px;"><span class="bubble-icon">'.Lang::$main['englishOnly'].'</span></div>')];

                foreach ($article as $text)
                    if (preg_match_all('/\[(npc|object|item|itemset|quest|spell|zone|faction|pet|achievement|title|holiday|class|race|skill|currency)=(\d+)[^\]]*\]/i', $text, $matches, PREG_SET_ORDER))
                        foreach ($matches as $match)
                            if ($type = array_search($match[1], Util::$typeStrings))
                            {
                                if (!isset($this->jsGlobals[$type]))
                                    $this->jsGlobals[$type] = [];

                                $this->jsGlobals[$type][] = $match[2];
                            }
            }
        }

        // fetch announcements
        if ($tv['query'][0] && !preg_match('/[^a-z]/i', $tv['query'][0]))
        {
            $ann = DB::Aowow()->Select('SELECT * FROM ?_announcements WHERE status = 1 AND (page = ? OR page = "*")', $tv['query'][0]);
            foreach ($ann as $k => $v)
            {
                if ($t = Util::localizedString($v, 'text'))
                    $ann[$k]['text'] = Util::jsEscape($t);
                else
                    unset($ann[$k]);
            }

            $tv['announcements'] = $ann;
        }

        $this->applyGlobals();

        $tv['mysql'] = DB::Aowow()->getStatistics();

        parent::display($tpl);
    }

    public function extendGlobalIds($type, $data)
    {
        if (!$type || !$data)
            return false;

        if (!isset($this->jsGlobals[$type]))
            $this->jsGlobals[$type] = [];

        if (is_array($data))
        {
            foreach ($data as $id)
                $this->jsGlobals[$type][] = (int)$id;
        }
        else if (is_numeric($data))
            $this->jsGlobals[$type][] = (int)$data;
        else
            return false;

        return true;
    }

    public function extendGlobalData($type, $data, $extra = null)
    {
        $this->initJSGlobal($type);
        $_ = &$this->_tpl_vars['jsGlobals'][$type];         // shorthand

        if (is_array($data) && $data)
            foreach ($data as $id => $set)
                if (!isset($_[1][$id]))
                    $_[1][$id] = $set;

        if (is_array($extra) && $extra)
            $_[2] = $extra;
    }

    private function initJSGlobal($type)
    {
        $jsg = &$this->_tpl_vars['jsGlobals'];              // shortcut

        if (isset($jsg[$type]))
            return;

        switch ($type)
        {                                                // [brickFile,  [data], [extra]]
            case TYPE_NPC:         $jsg[TYPE_NPC]         = ['creatures',    [], []]; break;
            case TYPE_OBJECT:      $jsg[TYPE_OBJECT]      = ['objects',      [], []]; break;
            case TYPE_ITEM:        $jsg[TYPE_ITEM]        = ['items',        [], []]; break;
            case TYPE_QUEST:       $jsg[TYPE_QUEST]       = ['quests',       [], []]; break;
            case TYPE_SPELL:       $jsg[TYPE_SPELL]       = ['spells',       [], []]; break;
            case TYPE_ZONE:        $jsg[TYPE_ZONE]        = ['zones',        [], []]; break;
            case TYPE_FACTION:     $jsg[TYPE_FACTION]     = ['factions',     [], []]; break;
            case TYPE_PET:         $jsg[TYPE_PET]         = ['pets',         [], []]; break;
            case TYPE_ACHIEVEMENT: $jsg[TYPE_ACHIEVEMENT] = ['achievements', [], []]; break;
            case TYPE_TITLE:       $jsg[TYPE_TITLE]       = ['titles',       [], []]; break;
            case TYPE_WORLDEVENT:  $jsg[TYPE_WORLDEVENT]  = ['holidays',     [], []]; break;
            case TYPE_CLASS:       $jsg[TYPE_CLASS]       = ['classes',      [], []]; break;
            case TYPE_RACE:        $jsg[TYPE_RACE]        = ['races',        [], []]; break;
            case TYPE_SKILL:       $jsg[TYPE_SKILL]       = ['skills',       [], []]; break;
            case TYPE_CURRENCY:    $jsg[TYPE_CURRENCY]    = ['currencies',   [], []]; break;
        }
    }

    private function applyGlobals()
    {
        foreach ($this->jsGlobals as $type => $ids)
        {
            foreach ($ids as $k => $id)                     // filter already generated data, maybe we can save a lookup or two
                if (isset($this->_tpl_vars['jsGlobals'][$type][1][$id]))
                    unset($ids[$k]);

            if (!$ids)
                continue;

            $this->initJSGlobal($type);
            $ids = array_unique($ids, SORT_NUMERIC);

            switch ($type)
            {
                case TYPE_NPC:         (new CreatureList(array(['ct.id', $ids], 0)))->addGlobalsToJscript($this, GLOBALINFO_SELF);      break;
                case TYPE_OBJECT:      (new GameobjectList(array(['gt.entry', $ids], 0)))->addGlobalsToJscript($this, GLOBALINFO_SELF); break;
                case TYPE_ITEM:        (new ItemList(array(['i.id', $ids], 0)))->addGlobalsToJscript($this, GLOBALINFO_SELF);           break;
                case TYPE_QUEST:       (new QuestList(array(['qt.entry', $ids], 0)))->addGlobalsToJscript($this, GLOBALINFO_SELF);      break;
                case TYPE_SPELL:       (new SpellList(array(['s.id', $ids], 0)))->addGlobalsToJscript($this, GLOBALINFO_SELF);          break;
                case TYPE_ZONE:        (new ZoneList(array(['z.id', $ids], 0)))->addGlobalsToJscript($this, GLOBALINFO_SELF);           break;
                case TYPE_FACTION:     (new FactionList(array(['id', $ids], 0)))->addGlobalsToJscript($this, GLOBALINFO_SELF);          break;
                case TYPE_PET:         (new PetList(array(['id', $ids], 0)))->addGlobalsToJscript($this, GLOBALINFO_SELF);              break;
                case TYPE_ACHIEVEMENT: (new AchievementList(array(['id', $ids], 0)))->addGlobalsToJscript($this, GLOBALINFO_SELF);      break;
                case TYPE_TITLE:       (new TitleList(array(['id', $ids], 0)))->addGlobalsToJscript($this, GLOBALINFO_SELF);            break;
                case TYPE_WORLDEVENT:  (new WorldEventList(array(['id', $ids], 0)))->addGlobalsToJscript($this, GLOBALINFO_SELF);       break;
                case TYPE_CLASS:       (new CharClassList(array(['id', $ids], 0)))->addGlobalsToJscript($this, GLOBALINFO_SELF);        break;
                case TYPE_RACE:        (new CharRaceList(array(['id', $ids], 0)))->addGlobalsToJscript($this, GLOBALINFO_SELF);         break;
                case TYPE_SKILL:       (new SkillList(array(['id', $ids], 0)))->addGlobalsToJscript($this, GLOBALINFO_SELF);            break;
                case TYPE_CURRENCY:    (new CurrencyList(array(['id', $ids], 0)))->addGlobalsToJscript($this, GLOBALINFO_SELF);         break;
            }
        }
    }

    public function notFound($subject)
    {
        $this->updatePageVars(array(
            'subject'  => Util::ucFirst($subject),
            'id'       => intVal($this->_tpl_vars['query'][1]),
            'notFound' => sprintf(Lang::$main['pageNotFound'], $subject)
        ));

        $this->assign('lang', Lang::$main);
        $this->assign('mysql', DB::Aowow()->getStatistics());

        $this->display('404.tpl');
        exit();
    }

    public function error()
    {
        $this->assign('lang', array_merge(Lang::$main, Lang::$error));
        $this->assign('mysql', DB::Aowow()->getStatistics());

        $this->display('error.tpl');
        exit();
    }

    public function brb()
    {
        $this->assign('lang', array_merge(Lang::$main, Lang::$error));

        $this->display('brb.tpl');
        exit();
    }

    // creates the cache file
    public function saveCache($key, $data, $filter = null)
    {
        if ($this->debugging)
            return;

        $file = $this->cache_dir.'data/'.$key;

        $cacheData = time()." ".AOWOW_REVISION."\n";
        $cacheData .= serialize(str_replace(["\n", "\t"], ['\n', '\t'], $data));

        if ($filter)
            $cacheData .= "\n".serialize($filter);

        file_put_contents($file, $cacheData);
    }

    // loads and evaluates the cache file
    public function loadCache($key, &$data, &$filter = null)
    {
        if ($this->debugging)
            return false;

        $cache = @file_get_contents($this->cache_dir.'data/'.$key);
        if (!$cache)
            return false;

        $cache = explode("\n", $cache);

        @list($time, $rev) = explode(' ', $cache[0]);
        $expireTime = $time + $this->config['page']['cacheTimer'];
        if ($expireTime <= time() || $rev < AOWOW_REVISION)
            return false;

        $data = str_replace(['\n', '\t'], ["\n", "\t"], unserialize($cache[1]));
        if (isset($cache[2]))
            $filter = unserialize($cache[2]);

        return true;
    }
}

class Util
{
    public static $resistanceFields         = array(
        null,           'resHoly',      'resFire',      'resNature',    'resFrost',     'resShadow',    'resArcane'
    );

    public static $rarityColorStings        = array(        // zero-indexed
        '9d9d9d',       'ffffff',       '1eff00',       '0070dd',       'a335ee',       'ff8000',       'e5cc80',       'e6cc80'
    );

    public static $localeStrings            = array(        // zero-indexed
        'enus',         null,           'frfr',         'dede',         null,           null,           'eses',         null,           'ruru'
    );

    public static $subDomains               = array(
        'www',          null,           'fr',           'de',           null,           null,           'es',           null,           'ru'
    );

    public static $typeStrings              = array(        // zero-indexed
        null,           'npc',          'object',       'item',         'itemset',      'quest',        'spell',        'zone',         'faction',
        'pet',          'achievement',  'title',        'event',        'class',        'race',         'skill',        null,           'currency'
    );

    public static $combatRatingToItemMod    = array(        // zero-indexed
        null,           12,             13,             14,             15,             16,             17,             18,             19,
        20,             21,             null,           null,           null,           null,           null,           null,           28,
        29,             30,             null,           null,           null,           37,             44
    );

    public static $gtCombatRatings          = array(
        12 => 1.5,      13 => 12,       14 => 15,       15 => 5,        16 => 10,       17 => 10,       18 => 8,        19 => 14,       20 => 14,
        21 => 14,       22 => 10,       23 => 10,       24 => 0,        25 => 0,        26 => 0,        27 => 0,        28 => 10,       29 => 10,
        30 => 10,       31 => 10,       32 => 14,       33 => 0,        34 => 0,        35 => 25,       36 => 10,       37 => 2.5,      44 => 3.756097412109376
    );

    public static $lvlIndepRating           = array(        // rating doesn't scale with level
        ITEM_MOD_MANA,                  ITEM_MOD_HEALTH,                ITEM_MOD_ATTACK_POWER,          ITEM_MOD_MANA_REGENERATION,     ITEM_MOD_SPELL_POWER,
        ITEM_MOD_HEALTH_REGEN,          ITEM_MOD_SPELL_PENETRATION,     ITEM_MOD_BLOCK_VALUE
    );

    public static $questClasses             = array(        // taken from old aowow: 0,1,2,3,8,10 may point to pointless mini-areas
        -2 =>  [    0],
         0 =>  [    1,     3,     4,     8,    10,    11,    12,    25,   28,   33,   36,   38,   40,   41,   44,   45,   46,   47,   51,   85,  130,  139,  267,  279, 1497, 1519, 1537, 2257, 3430, 3433, 3487, 4080],
         1 =>  [   14,    15,    16,    17,   141,   148,   215,   331,  357,  361,  400,  405,  406,  440,  490,  493,  618, 1216, 1377, 1637, 1638, 1657, 3524, 3525, 3557],
         2 =>  [  133,   206,   209,   491,   717,   718,   719,   722,  796,  978, 1196, 1337, 1417, 1581, 1583, 1584, 1941, 2017, 2057, 2100, 2366, 2367, 2437, 2557, 3477, 3562, 3713, 3714, 3715, 3716, 3717, 3789, 3790, 3791, 3792, 3845, 3846, 3847, 3849, 3905, 4095, 4100, 4120, 4196, 4228, 4264, 4272, 4375, 4415, 4494, 4723],
         3 =>  [   19,  2159,  2562,  2677,  2717,  3428,  3429,  3456, 3606, 3805, 3836, 3840, 3842, 4273, 4500, 4722, 4812],
         4 =>  [ -372,  -263,  -262,  -261,  -162,  -161,  -141,   -82,  -81,  -61],
         5 =>  [ -373,  -371,  -324,  -304,  -264,  -201,  -182,  -181, -121, -101,  -24],
         6 =>  [  -25,  2597,  3277,  3358,  3820,  4384,  4710],
         7 =>  [ -368,  -367,  -365,  -344,  -241,    -1],
         8 =>  [ 3483,  3518,  3519,  3520,  3521,  3522,  3523,  3679, 3703],
         9 =>  [-1008, -1007, -1006, -1005, -1004, -1003, -1002, -1001, -375, -374, -370, -369, -366, -364, -284,  -41,  -22],
        10 =>  [   65,    66,    67,   210,   394,   495,  3537,  3711, 4024, 4197, 4395]
    );

    /*  why:
        Because petSkills (and ranged weapon skills) are the only ones with more than two skillLines attached. Because Left Joining ?_spell with ?_skillLineAbility  causes more trouble than it has uses.
        Because this is more or less the only reaonable way to fit all that information into one database field, so..
        .. the indizes of this array are bits of skillLine2OrMask in ?_spell if skillLineId1 is negative
    */
    public static $skillLineMask            = array(        // idx => [familyId, skillLineId]
        -1 => array(                                        // Pets (Hunter)
            [ 1, 208],          [ 2, 209],          [ 3, 203],          [ 4, 210],          [ 5, 211],          [ 6, 212],          [ 7, 213],  // Wolf,       Cat,          Spider,       Bear,        Boar,      Crocolisk,    Carrion Bird
            [ 8, 214],          [ 9, 215],          [11, 217],          [12, 218],          [20, 236],          [21, 251],          [24, 653],  // Crab,       Gorilla,      Raptor,       Tallstrider, Scorpid,   Turtle,       Bat
            [25, 654],          [26, 655],          [27, 656],          [30, 763],          [31, 767],          [32, 766],          [33, 765],  // Hyena,      Bird of Prey, Wind Serpent, Dragonhawk,  Ravager,   Warp Stalker, Sporebat
            [34, 764],          [35, 768],          [37, 775],          [38, 780],          [39, 781],          [41, 783],          [42, 784],  // Nether Ray, Serpent,      Moth,         Chimaera,    Devilsaur, Silithid,     Worm
            [43, 786],          [44, 785],          [45, 787],          [46, 788]                                                               // Rhino,      Wasp,         Core Hound,   Spirit Beast
        ),
        -2 => array(                                        // Pets (Warlock)
            [15, 189],          [16, 204],          [17, 205],          [19, 207],          [23, 188],          [29, 761]                       // Felhunter,  Voidwalker,   Succubus,     Doomguard,   Imp,       Felguard
        ),
        -3 => array(                                        // Ranged Weapons
            [null, 45],         [null, 46],         [null, 226]                                                                                 // Bow,         Gun,         Crossbow
        )
    );

    public static $trainerTemplates         = array(        // TYPE => Id => templateList
        TYPE_CLASS => array(
              1 => [-200001, -200002],                      // Warrior
              2 => [-200003, -200004, -200020, -200021],    // Paladin
              3 => [-200013, -200014],                      // Hunter
              4 => [-200015, -200016],                      // Rogue
              5 => [-200011, -200012],                      // Priest
              6 => [-200019],                               // DK
              7 => [-200017, -200018],                      // Shaman (HighlevelAlly Id missing..?)
              8 => [-200007, -200008],                      // Mage
              9 => [-200009, -200010],                      // Warlock
             11 => [-200005, -200006]                       // Druid
        ),
        TYPE_SKILL => array(
            171 => [-201001, -201002, -201003],             // Alchemy
            164 => [-201004, -201005, -201006, -201007, -201008],// Blacksmithing
            333 => [-201009, -201010, -201011],             // Enchanting
            202 => [-201012, -201013, -201014, -201015, -201016, -201017], // Engineering
            182 => [-201018, -201019, -201020],             // Herbalism
            773 => [-201021, -201022, -201023],             // Inscription
            755 => [-201024, -201025, -201026],             // Jewelcrafting
            165 => [-201027, -201028, -201029, -201030, -201031, -201032], // Leatherworking
            186 => [-201033, -201034, -201035],             // Mining
            393 => [-201036, -201037, -201038],             // Skinning
            197 => [-201039, -201040, -201041, -201042],    // Tailoring
            356 => [-202001, -202002, -202003],             // Fishing
            185 => [-202004, -202005, -202006],             // Cooking
            129 => [-202007, -202008, -202009],             // First Aid
            129 => [-202010, -202011, -202012]              // Riding
        )
    );

    public static $sockets                  = array(        // jsStyle Strings
        'meta',                         'red',                          'yellow',                       'blue'
    );

    public static $itemMods                 = array(        // zero-indexed; "mastrtng": unused mastery; _[a-z] => taken mods..
        'dmg',              'mana',             'health',           'agi',              'str',              'int',              'spi',
        'sta',              'energy',           'rage',             'focus',            'runicpwr',         'defrtng',          'dodgertng',
        'parryrtng',        'blockrtng',        'mlehitrtng',       'rgdhitrtng',       'splhitrtng',       'mlecritstrkrtng',  'rgdcritstrkrtng',
        'splcritstrkrtng',  '_mlehitrtng',      '_rgdhitrtng',      '_splhitrtng',      '_mlecritstrkrtng', '_rgdcritstrkrtng', '_splcritstrkrtng',
        'mlehastertng',     'rgdhastertng',     'splhastertng',     'hitrtng',          'critstrkrtng',     '_hitrtng',         '_critstrkrtng',
        'resirtng',         'hastertng',        'exprtng',          'atkpwr',           'rgdatkpwr',        'feratkpwr',        'splheal',
        'spldmg',           'manargn',          'armorpenrtng',     'splpwr',           'healthrgn',        'splpen',           'block',                                          // ITEM_MOD_BLOCK_VALUE
        'mastrtng',         'armor',            'firres',           'frores',           'holres',           'shares',           'natres',
        'arcres',           'firsplpwr',        'frosplpwr',        'holsplpwr',        'shasplpwr',        'natsplpwr',        'arcsplpwr'
    );

    public static $itemFilter               = array(
         20 => 'str',                21 => 'agi',                23 => 'int',                22 => 'sta',                24 => 'spi',                25 => 'arcres',             26 => 'firres',             27 => 'natres',
         28 => 'frores',             29 => 'shares',             30 => 'holres',             37 => 'mleatkpwr',          32 => 'dps',                35 => 'damagetype',         33 => 'dmgmin1',            34 => 'dmgmax1',
         36 => 'speed',              38 => 'rgdatkpwr',          39 => 'rgdhitrtng',         40 => 'rgdcritstrkrtng',    41 => 'armor',              44 => 'blockrtng',          43 => 'block',              42 => 'defrtng',
         45 => 'dodgertng',          46 => 'parryrtng',          48 => 'splhitrtng',         49 => 'splcritstrkrtng',    50 => 'splheal',            51 => 'spldmg',             52 => 'arcsplpwr',          53 => 'firsplpwr',
         54 => 'frosplpwr',          55 => 'holsplpwr',          56 => 'natsplpwr',          60 => 'healthrgn',          61 => 'manargn',            57 => 'shasplpwr',          77 => 'atkpwr',             78 => 'mlehastertng',
         79 => 'resirtng',           84 => 'mlecritstrkrtng',    94 => 'splpen',             95 => 'mlehitrtng',         96 => 'critstrkrtng',       97 => 'feratkpwr',         100 => 'nsockets',          101 => 'rgdhastertng',
        102 => 'splhastertng',      103 => 'hastertng',         114 => 'armorpenrtng',      115 => 'health',            116 => 'mana',              117 => 'exprtng',           119 => 'hitrtng',           123 => 'splpwr',
        134 => 'mledps',            135 => 'mledmgmin',         136 => 'mledmgmax',         137 => 'mlespeed',          138 => 'rgddps',            139 => 'rgddmgmin',         140 => 'rgddmgmax',         141 => 'rgdspeed'
    );

    public static $ssdMaskFields            = array(
        'shoulderMultiplier',           'trinketMultiplier',            'weaponMultiplier',             'primBudged',
        'rangedMultiplier',             'clothShoulderArmor',           'leatherShoulderArmor',         'mailShoulderArmor',
        'plateShoulderArmor',           'weaponDPS1H',                  'weaponDPS2H',                  'casterDPS1H',
        'casterDPS2H',                  'rangedDPS',                    'wandDPS',                      'spellPower',
        null,                           null,                           'tertBudged',                   'clothCloakArmor',
        'clothChestArmor',              'leatherChestArmor',            'mailChestArmor',               'plateChestArmor'
    );

    public static $dateFormatShort          = "Y/m/d";
    public static $dateFormatLong           = "Y/m/d H:i:s";

    public static $changeLevelString        = '<a href="javascript:;" onmousedown="return false" class="tip" style="color: white; cursor: pointer" onclick="$WH.g_staticTooltipLevelClick(this, null, 0)" onmouseover="$WH.Tooltip.showAtCursor(event, \'<span class=\\\'q2\\\'>\' + LANG.tooltip_changelevel + \'</span>\')" onmousemove="$WH.Tooltip.cursorUpdate(event)" onmouseout="$WH.Tooltip.hide()"><!--lvl-->%s</a>';

    public static $setRatingLevelString     = '<a href="javascript:;" onmousedown="return false" class="tip" style="color: white; cursor: pointer" onclick="$WH.g_setRatingLevel(this, %s, %s, %s)" onmouseover="$WH.Tooltip.showAtCursor(event, \'<span class=\\\'q2\\\'>\' + LANG.tooltip_changelevel + \'</span>\')" onmousemove="$WH.Tooltip.cursorUpdate(event)" onmouseout="$WH.Tooltip.hide()">%s</a>';

    public static $filterResultString       = '$$WH.sprintf(LANG.lvnote_filterresults, \'%s\')';
    public static $tryFilteringString       = '$$WH.sprintf(%s, %s, %s) + LANG.dash + LANG.lvnote_tryfiltering.replace(\'<a>\', \'<a href="javascript:;" onclick="fi_toggle()">\')';
    public static $tryNarrowingString       = '$$WH.sprintf(%s, %s, %s) + LANG.dash + LANG.lvnote_trynarrowing';
    public static $setCriteriaString        = "fi_setCriteria(%s, %s, %s);\n";

    public static $dfnString                = '<dfn title="%s" class="w">%s</dfn>';

    public static $expansionString          = array(        // 3 & 4 unused .. obviously
        null,           'bc',           'wotlk',            'cata',                'mop'
    );

    public static $class2SpellFamily        = array(
    //  null    Warrior Paladin Hunter  Rogue   Priest  DK      Shaman  Mage    Warlock null    Druid
        null,   4,      10,     9,      8,      6,      15,     11,     3,      5,      null,   7
    );

    // from DurabilityQuality.dbc
    public static $itemDurabilityQualityMod = array(
        null,   1.0,    0.6,    1.0,    0.8,    1.0,    1.0,    1.2,    1.25,   1.44,   2.5,    1.728,  3.0,    0.0,    0.0,    1.2,    1.25
    );

    public static $lootTemplates = array(
        LOOT_REFERENCE,     // internal
        LOOT_ITEM,          // item
        LOOT_DISENCHANT,    // item
        LOOT_PROSPECTING,   // item
        LOOT_MILLING,       // item
        LOOT_CREATURE,      // npc
        LOOT_PICKPOCKET,    // npc
        LOOT_SKINNING,      // npc (see its flags for mining, herbing or actual skinning)
        LOOT_FISHING,       // zone
        LOOT_GAMEOBJECT,    // object
        LOOT_QUEST,         // quest (mail rewards)
        LOOT_SPELL          // spell
    );

    // todo: translate and move to Lang
    public static $spellEffectStrings       = array(
          0 => 'None',
          1 => 'Instakill',
          2 => 'School Damage',
          3 => 'Dummy',
          4 => 'Portal Teleport',
          5 => 'Teleport Units',
          6 => 'Apply Aura',
          7 => 'Environmental Damage',
          8 => 'Power Drain',
          9 => 'Health Leech',
         10 => 'Heal',
         11 => 'Bind',
         12 => 'Portal',
         13 => 'Ritual Base',
         14 => 'Ritual Specialize',
         15 => 'Ritual Activate Portal',
         16 => 'Quest Complete',
         17 => 'Weapon Damage NoSchool',
         18 => 'Resurrect',
         19 => 'Add Extra Attacks',
         20 => 'Dodge',
         21 => 'Evade',
         22 => 'Parry',
         23 => 'Block',
         24 => 'Create Item',
         25 => 'Can Use Weapon',
         26 => 'Defense',
         27 => 'Persistent Area Aura',
         28 => 'Summon',
         29 => 'Leap',
         30 => 'Energize',
         31 => 'Weapon Damage Percent',
         32 => 'Trigger Missile',
         33 => 'Open Lock',
         34 => 'Summon Change Item',
         35 => 'Apply Area Aura Party',
         36 => 'Learn Spell',
         37 => 'Spell Defense',
         38 => 'Dispel',
         39 => 'Language',
         40 => 'Dual Wield',
         41 => 'Jump',
         42 => 'Jump Dest',
         43 => 'Teleport Units Face Caster',
         44 => 'Skill Step',
         45 => 'Add Honor',
         46 => 'Spawn',
         47 => 'Trade Skill',
         48 => 'Stealth',
         49 => 'Detect',
         50 => 'Trans Door',
         51 => 'Force Critical Hit',
         52 => 'Guarantee Hit',
         53 => 'Enchant Item Permanent',
         54 => 'Enchant Item Temporary',
         55 => 'Tame Creature',
         56 => 'Summon Pet',
         57 => 'Learn Pet Spell',
         58 => 'Weapon Damage Flat',
         59 => 'Create Random Item',
         60 => 'Proficiency',
         61 => 'Send Event',
         62 => 'Power Burn',
         63 => 'Threat',
         64 => 'Trigger Spell',
         65 => 'Apply Area Aura Raid',
         66 => 'Create Mana Gem',
         67 => 'Heal Max Health',
         68 => 'Interrupt Cast',
         69 => 'Distract',
         70 => 'Pull',
         71 => 'Pickpocket',
         72 => 'Add Farsight',
         73 => 'Untrain Talents',
         74 => 'Apply Glyph',
         75 => 'Heal Mechanical',
         76 => 'Summon Object Wild',
         77 => 'Script Effect',
         78 => 'Attack',
         79 => 'Sanctuary',
         80 => 'Add Combo Points',
         81 => 'Create House',
         82 => 'Bind Sight',
         83 => 'Duel',
         84 => 'Stuck',
         85 => 'Summon Player',
         86 => 'Activate Object',
         87 => 'WMO Damage',
         88 => 'WMO Repair',
         89 => 'WMO Change',
         90 => 'Kill Credit',
         91 => 'Threat All',
         92 => 'Enchant Held Item',
         93 => 'Force Deselect',
         94 => 'Self Resurrect',
         95 => 'Skinning',
         96 => 'Charge',
         97 => 'Cast Button',
         98 => 'Knock Back',
         99 => 'Disenchant',
        100 => 'Inebriate',
        101 => 'Feed Pet',
        102 => 'Dismiss Pet',
        103 => 'Reputation',
        104 => 'Summon Object Slot1',
        105 => 'Summon Object Slot2',
        106 => 'Summon Object Slot3',
        107 => 'Summon Object Slot4',
        108 => 'Dispel Mechanic',
        109 => 'Summon Dead Pet',
        110 => 'Destroy All Totems',
        111 => 'Durability Damage',
        112 => 'Summon Demon',
        113 => 'Resurrect Flat',
        114 => 'Attack Me',
        115 => 'Durability Damage Percent',
        116 => 'Skin Player Corpse',
        117 => 'Spirit Heal',
        118 => 'Skill',
        119 => 'Apply Area Aura Pet',
        120 => 'Teleport Graveyard',
        121 => 'Weapon Damage Normalized',
        122 => 'Unknown Effect',
        123 => 'Send Taxi',
        124 => 'Pull Towards',
        125 => 'Modify Threat Percent',
        126 => 'Steal Beneficial Buff',
        127 => 'Prospecting',
        128 => 'Apply Area Aura Friend',
        129 => 'Apply Area Aura Enemy',
        130 => 'Redirect Threat',
        131 => 'Unknown Effect',
        132 => 'Play Music',
        133 => 'Unlearn Specialization',
        134 => 'Kill Credit2',
        135 => 'Call Pet',
        136 => 'Heal Percent',
        137 => 'Energize Percent',
        138 => 'Leap Back',
        139 => 'Clear Quest',
        140 => 'Force Cast',
        141 => 'Force Cast With Value',
        142 => 'Trigger Spell With Value',
        143 => 'Apply Area Aura Owner',
        144 => 'Knock Back Dest',
        145 => 'Pull Towards Dest',
        146 => 'Activate Rune',
        147 => 'Quest Fail',
        148 => 'Unknown Effect',
        149 => 'Charge Dest',
        150 => 'Quest Start',
        151 => 'Trigger Spell 2',
        152 => 'Unknown Effect',
        153 => 'Create Tamed Pet',
        154 => 'Discover Taxi',
        155 => 'Dual Wield 2H Weapons',
        156 => 'Enchant Item Prismatic',
        157 => 'Create Item 2',
        158 => 'Milling',
        159 => 'Allow Rename Pet',
        160 => 'Unknown Effect',
        161 => 'Talent Spec Count',
        162 => 'Talent Spec Select',
        163 => 'Unknown Effect',
        164 => 'Remove Aura'
    );

    public static $spellAuraStrings         = array(
        0 => 'None',
        1 => 'Bind Sight',
        2 => 'Mod Possess',
        3 => 'Periodic Damage',
        4 => 'Dummy',
        5 => 'Mod Confuse',
        6 => 'Mod Charm',
        7 => 'Mod Fear',
        8 => 'Periodic Heal',
        9 => 'Mod Attack Speed',
        10 => 'Mod Threat',
        11 => 'Taunt',
        12 => 'Stun',
        13 => 'Mod Damage Done Flat',
        14 => 'Mod Damage Taken Flat',
        15 => 'Damage Shield',
        16 => 'Mod Stealth',
        17 => 'Mod Stealth Detection',
        18 => 'Mod Invisibility',
        19 => 'Mod Invisibility Detection',
        20 => 'Mod Health Percent',
        21 => 'Mod Power Percent',
        22 => 'Mod Resistance Flat',
        23 => 'Periodic Trigger Spell',
        24 => 'Periodic Energize',
        25 => 'Pacify',
        26 => 'Root',
        27 => 'Silence',
        28 => 'Reflect Spells',
        29 => 'Mod Stat Flat',
        30 => 'Mod Skill',
        31 => 'Mod Increase Speed',
        32 => 'Mod Increase Mounted Speed',
        33 => 'Mod Decrease Speed',
        34 => 'Mod Increase Health',
        35 => 'Mod Increase Power',
        36 => 'Shapeshift',
        37 => 'Spell Effect Immunity',
        38 => 'Spell Aura Immunity',
        39 => 'School Immunity',
        40 => 'Damage Immunity',
        41 => 'Dispel Immunity',
        42 => 'Proc Trigger Spell',
        43 => 'Proc Trigger Damage',
        44 => 'Track Creatures',
        45 => 'Track Resources',
        46 => 'Mod Parry Skill',
        47 => 'Mod Parry Percent',
        48 => 'Unknown Aura',
        49 => 'Mod Dodge Percent',
        50 => 'Mod Critical Healing Amount',
        51 => 'Mod Block Percent',
        52 => 'Mod Physical Crit Percent',
        53 => 'Periodic Health Leech',
        54 => 'Mod Hit Chance',
        55 => 'Mod Spell Hit Chance',
        56 => 'Transform',
        57 => 'Mod Spell Crit Chance',
        58 => 'Mod Increase Swim Speed',
        59 => 'Mod Damage Done Versus Creature',
        60 => 'Pacify Silence',
        61 => 'Mod Scale',
        62 => 'Periodic Health Funnel',
        63 => 'Periodic Mana Funnel',
        64 => 'Periodic Mana Leech',
        65 => 'Mod Casting Speed (not stacking)',
        66 => 'Feign Death',
        67 => 'Disarm',
        68 => 'Stalked',
        69 => 'School Absorb',
        70 => 'Extra Attacks',
        71 => 'Mod Spell Crit Chance School',
        72 => 'Mod Power Cost School Percent',
        73 => 'Mod Power Cost School Flat',
        74 => 'Reflect Spells School',
        75 => 'Language',
        76 => 'Far Sight',
        77 => 'Mechanic Immunity',
        78 => 'Mounted',
        79 => 'Mod Damage Done Percent',
        80 => 'Mod Stat Percent',
        81 => 'Split Damage Percent',
        82 => 'Water Breathing',
        83 => 'Mod Base Resistance Flat',
        84 => 'Mod Health Regeneration',
        85 => 'Mod Power Regeneration',
        86 => 'Channel Death Item',
        87 => 'Mod Damage Taken Percent',
        88 => 'Mod Health Regeneration Percent',
        89 => 'Periodic Damage Percent',
        90 => 'Mod Resist Chance',
        91 => 'Mod Detect Range',
        92 => 'Prevent Fleeing',
        93 => 'Unattackable',
        94 => 'Interrupt Regeneration',
        95 => 'Ghost',
        96 => 'Spell Magnet',
        97 => 'Mana Shield',
        98 => 'Mod Skill Value',
        99 => 'Mod Attack Power',
        100 => 'Auras Visible',
        101 => 'Mod Resistance Percent',
        102 => 'Mod Melee Attack Power Versus',
        103 => 'Mod Total Threat',
        104 => 'Water Walk',
        105 => 'Feather Fall',
        106 => 'Hover',
        107 => 'Add Flat Modifier',
        108 => 'Add Percent Modifier',
        109 => 'Add Target Trigger',
        110 => 'Mod Power Regeneration Percent',
        111 => 'Add Caster Hit Trigger',
        112 => 'Override Class Scripts',
        113 => 'Mod Ranged Damage Taken Flat',
        114 => 'Mod Ranged Damage Taken Percent',
        115 => 'Mod Healing',
        116 => 'Mod Regeneration During Combat',
        117 => 'Mod Mechanic Resistance',
        118 => 'Mod Healing Taken Percent',
        119 => 'Share Pet Tracking',
        120 => 'Untrackable',
        121 => 'Empathy',
        122 => 'Mod Offhand Damage Percent',
        123 => 'Mod Target Resistance',
        124 => 'Mod Ranged Attack Power',
        125 => 'Mod Melee Damage Taken Flat',
        126 => 'Mod Melee Damage Taken Percent',
        127 => 'Ranged Attack Power Attacker Bonus',
        128 => 'Possess Pet',
        129 => 'Mod Speed Always',
        130 => 'Mod Mounted Speed Always',
        131 => 'Mod Ranged Attack Power Versus',
        132 => 'Mod Increase Energy Percent',
        133 => 'Mod Increase Health Percent',
        134 => 'Mod Mana Regeneration Interrupt',
        135 => 'Mod Healing Done Flat',
        136 => 'Mod Healing Done Percent',
        137 => 'Mod Total Stat Percentage',
        138 => 'Mod Melee Haste',
        139 => 'Force Reaction',
        140 => 'Mod Ranged Haste',
        141 => 'Mod Ranged Ammo Haste',
        142 => 'Mod Base Resistance Percent',
        143 => 'Mod Resistance Exclusive',
        144 => 'Safe Fall',
        145 => 'Mod Pet Talent Points',
        146 => 'Allow Tame Pet Type',
        147 => 'Mechanic Immunity Mask',
        148 => 'Retain Combo Points',
        149 => 'Reduce Pushback',
        150 => 'Mod Shield Blockvalue Percent',
        151 => 'Track Stealthed',
        152 => 'Mod Detected Range',
        153 => 'Split Damage Flat',
        154 => 'Mod Stealth Level',
        155 => 'Mod Water Breathing',
        156 => 'Mod Reputation Gain',
        157 => 'Pet Damage Multi',
        158 => 'Mod Shield Blockvalue',
        159 => 'No PvP Credit',
        160 => 'Mod AoE Avoidance',
        161 => 'Mod Health Regeneration In Combat',
        162 => 'Power Burn Mana',
        163 => 'Mod Crit Damage Bonus',
        164 => 'Unknown Aura',
        165 => 'Melee Attack Power Attacker Bonus',
        166 => 'Mod Attack Power Percent',
        167 => 'Mod Ranged Attack Power Percent',
        168 => 'Mod Damage Done Versus',
        169 => 'Mod Crit Percent Versus',
        170 => 'Change Model',
        171 => 'Mod Speed (not stacking)',
        172 => 'Mod Mounted Speed (not stacking)',
        173 => 'Unknown Aura',
        174 => 'Mod Spell Damage Of Stat Percent',
        175 => 'Mod Spell Healing Of Stat Percent',
        176 => 'Spirit Of Redemption',
        177 => 'AoE Charm',
        178 => 'Mod Debuff Resistance',
        179 => 'Mod Attacker Spell Crit Chance',
        180 => 'Mod Spell Damage Versus',
        181 => 'Unknown Aura',
        182 => 'Mod Resistance Of Stat Percent',
        183 => 'Mod Critical Threat',
        184 => 'Mod Attacker Melee Hit Chance',
        185 => 'Mod Attacker Ranged Hit Chance',
        186 => 'Mod Attacker Spell Hit Chance',
        187 => 'Mod Attacker Melee Crit Chance',
        188 => 'Mod Attacker Ranged Crit Chance',
        189 => 'Mod Rating',
        190 => 'Mod Faction Reputation Gain',
        191 => 'Use Normal Movement Speed',
        192 => 'Mod Melee Ranged Haste',
        193 => 'Mod Haste',
        194 => 'Mod Target Absorb School',
        195 => 'Mod Target Ability Absorb School',
        196 => 'Mod Cooldown',
        197 => 'Mod Attacker Spell And Weapon Crit Chance',
        198 => 'Unknown Aura',
        199 => 'Mod Increases Spell Percent to Hit',
        200 => 'Mod XP Percent',
        201 => 'Fly',
        202 => 'Ignore Combat Result',
        203 => 'Mod Attacker Melee Crit Damage',
        204 => 'Mod Attacker Ranged Crit Damage',
        205 => 'Mod School Crit Damage Taken',
        206 => 'Mod Increase Vehicle Flight Speed',
        207 => 'Mod Increase Mounted Flight Speed',
        208 => 'Mod Increase Flight Speed',
        209 => 'Mod Mounted Flight Speed Always',
        210 => 'Mod Vehicle Speed Always',
        211 => 'Mod Flight Speed (not stacking)',
        212 => 'Mod Ranged Attack Power Of Stat Percent',
        213 => 'Mod Rage from Damage Dealt',
        214 => 'Tamed Pet Passive',
        215 => 'Arena Preparation',
        216 => 'Haste Spells',
        217 => 'Killing Spree',
        218 => 'Haste Ranged',
        219 => 'Mod Mana Regeneration from Stat',
        220 => 'Mod Rating from Stat',
        221 => 'Ignore Threat',
        222 => 'Unknown Aura',
        223 => 'Raid Proc from Charge',
        224 => 'Unknown Aura',
        225 => 'Raid Proc from Charge With Value',
        226 => 'Periodic Dummy',
        227 => 'Periodic Trigger Spell With Value',
        228 => 'Detect Stealth',
        229 => 'Mod AoE Damage Avoidance',
        230 => 'Mod Increase Health',
        231 => 'Proc Trigger Spell With Value',
        232 => 'Mod Mechanic Duration',
        233 => 'Mod Display Model',
        234 => 'Mod Mechanic Duration (not stacking)',
        235 => 'Mod Dispel Resist',
        236 => 'Control Vehicle',
        237 => 'Mod Spell Damage Of Attack Power',
        238 => 'Mod Spell Healing Of Attack Power',
        239 => 'Mod Scale 2',
        240 => 'Mod Expertise',
        241 => 'Force Move Forward',
        242 => 'Mod Spell Damage from Healing',
        243 => 'Mod Faction',
        244 => 'Comprehend Language',
        245 => 'Mod Aura Duration By Dispel',
        246 => 'Mod Aura Duration By Dispel (not stacking)',
        247 => 'Clone Caster',
        248 => 'Mod Combat Result Chance',
        249 => 'Convert Rune',
        250 => 'Mod Increase Health 2',
        251 => 'Mod Enemy Dodge',
        252 => 'Mod Speed Slow All',
        253 => 'Mod Block Crit Chance',
        254 => 'Mod Disarm Offhand',
        255 => 'Mod Mechanic Damage Taken Percent',
        256 => 'No Reagent Use',
        257 => 'Mod Target Resist By Spell Class',
        258 => 'Mod Spell Visual',
        259 => 'Mod HoT Percent',
        260 => 'Screen Effect',
        261 => 'Phase',
        262 => 'Ability Ignore Aurastate',
        263 => 'Allow Only Ability',
        264 => 'Unknown Aura',
        265 => 'Unknown Aura',
        266 => 'Unknown Aura',
        267 => 'Mod Immune Aura Apply School',
        268 => 'Mod Attack Power Of Stat Percent',
        269 => 'Mod Ignore Target Resist',
        270 => 'Mod Ability Ignore Target Resist',
        271 => 'Mod Damage Taken Percent From Caster',
        272 => 'Ignore Melee Reset',
        273 => 'X Ray',
        274 => 'Ability Consume No Ammo',
        275 => 'Mod Ignore Shapeshift',
        276 => 'Mod Mechanic Damage Done Percent',
        277 => 'Mod Max Affected Targets',
        278 => 'Mod Disarm Ranged',
        279 => 'Initialize Images',
        280 => 'Mod Armor Penetration Percent',
        281 => 'Mod Honor Gain Percent',
        282 => 'Mod Base Health Percent',
        283 => 'Mod Healing Received',
        284 => 'Linked',
        285 => 'Mod Attack Power Of Armor',
        286 => 'Ability Periodic Crit',
        287 => 'Deflect Spells',
        288 => 'Ignore Hit Direction',
        289 => 'Unknown Aura',
        290 => 'Mod Crit Percent',
        291 => 'Mod XP Quest Percent',
        292 => 'Open Stable',
        293 => 'Override Spells',
        294 => 'Prevent Power Regeneration',
        295 => 'Unknown Aura',
        296 => 'Set Vehicle Id',
        297 => 'Block Spell Family',
        298 => 'Strangulate',
        299 => 'Unknown Aura',
        300 => 'Share Damage Percent',
        301 => 'School Heal Absorb',
        302 => 'Unknown Aura',
        303 => 'Mod Damage Done Versus Aurastate',
        304 => 'Mod Fake Inebriate',
        305 => 'Mod Minimum Speed',
        306 => 'Unknown Aura',
        307 => 'Heal Absorb Test',
        308 => 'Hunter Trap',
        309 => 'Unknown Aura',
        310 => 'Mod Creature AoE Damage Avoidance',
        311 => 'Unknown Aura',
        312 => 'Unknown Aura',
        313 => 'Unknown Aura',
        314 => 'Prevent Ressurection',
        315 => 'Underwater Walking',
        316 => 'Periodic Haste'
    );

    public static $bgImagePath              = array (
        'tiny'   => 'style="background-image: url(%s/images/icons/tiny/%s.gif)"',
        'small'  => 'style="background-image: url(%s/images/icons/small/%s.jpg)"',
        'medium' => 'style="background-image: url(%s/images/icons/medium/%s.jpg)"',
        'large'  => 'style="background-image: url(%s/images/icons/large/%s.jpg)"',
    );

    public static $tcEncoding               = '0zMcmVokRsaqbdrfwihuGINALpTjnyxtgevElBCDFHJKOPQSUWXYZ123456789';

    private static $execTime = 0.0;

    public static function execTime($set = false)
    {
        if ($set)
        {
            self::$execTime = microTime(true);
            return;
        }

        if (!self::$execTime)
            return;

        $newTime        = microTime(true);
        $tDiff          = $newTime - self::$execTime;
        self::$execTime = $newTime;

        return self::formatTime($tDiff * 1000, true);
    }

    public static function getBuyoutForItem($itemId)
    {
        if (!$itemId)
            return 0;

        // try, when having filled char-DB at hand
        // return DB::Characters()->selectCell('SELECT SUM(a.buyoutprice) / SUM(ii.count) FROM auctionhouse a JOIN item_instance ii ON ii.guid = a.itemguid WHERE ii.itemEntry = ?d', $itemId);
        return 0;
    }

    public static function formatMoney($qty)
    {
        $money = '';

        if ($qty >= 10000)
        {
            $g = floor($qty / 10000);
            $money .= '<span class="moneygold">'.$g.'</span> ';
            $qty -= $g * 10000;
        }

        if ($qty >= 100)
        {
            $s = floor($qty / 100);
            $money .= '<span class="moneysilver">'.$s.'</span> ';
            $qty -= $s * 100;
        }

        if ($qty > 0)
            $money .= '<span class="moneycopper">'.$qty.'</span>';

        return $money;
    }

    public static function parseTime($sec)
    {
        $time = ['d' => 0, 'h' => 0, 'm' => 0, 's' => 0, 'ms' => 0];

        if ($sec >= 3600 * 24)
        {
            $time['d'] = floor($sec / 3600 / 24);
            $sec -= $time['d'] * 3600 * 24;
        }

        if ($sec >= 3600)
        {
            $time['h'] = floor($sec / 3600);
            $sec -= $time['h'] * 3600;
        }

        if ($sec >= 60)
        {
            $time['m'] = floor($sec / 60);
            $sec -= $time['m'] * 60;
        }

        if ($sec > 0)
        {
            $time['s'] = (int)$sec;
            $sec -= $time['s'];
        }

        if (($sec * 1000) % 1000)
            $time['ms'] = (int)($sec * 1000);

        return $time;
    }

    public static function formatTime($base, $short = false)
    {
        $s = self::parseTime($base / 1000);
        $fmt = [];

        if ($short)
        {
            if ($_ = round($s['d']))
                return $_." ".Lang::$timeUnits['ab'][3];
            if ($_ = round($s['h']))
                return $_." ".Lang::$timeUnits['ab'][4];
            if ($_ = round($s['m']))
                return $_." ".Lang::$timeUnits['ab'][5];
            if ($_ = round($s['s'] + $s['ms'] / 1000, 2))
                return $_." ".Lang::$timeUnits['ab'][6];
            if ($s['ms'])
                return $s['ms']." ".Lang::$timeUnits['ab'][7];

            return '0 '.Lang::$timeUnits['ab'][6];
        }
        else
        {
            if ($s['d'])
                return round($s['d'] + $s['h'] / 24, 2)." ".Lang::$timeUnits[$s['d'] == 1 && !$s['h'] ? 'sg' : 'pl'][3];
            if ($s['h'])
                return round($s['h'] + $s['m'] / 60, 2)." ".Lang::$timeUnits[$s['h'] == 1 && !$s['m'] ? 'sg' : 'pl'][4];
            if ($s['m'])
                return round($s['m'] + $s['s'] / 60, 2)." ".Lang::$timeUnits[$s['m'] == 1 && !$s['s'] ? 'sg' : 'pl'][5];
            if ($s['s'])
                return round($s['s'] + $s['ms'] / 1000, 2)." ".Lang::$timeUnits[$s['s'] == 1 && !$s['ms'] ? 'sg' : 'pl'][6];
            if ($s['ms'])
                return $s['ms']." ".Lang::$timeUnits[$s['ms'] == 1 ? 'sg' : 'pl'][7];

            return '0 '.Lang::$timeUnits['pl'][6];
        }
    }

    public static function itemModByRatingMask($mask)
    {
        if (($mask & 0x1C000) == 0x1C000)                   // special case resilience
            return ITEM_MOD_RESILIENCE_RATING;

        if (($mask & 0x00E0) == 0x00E0)                     // special case hit rating
            return ITEM_MOD_HIT_RATING;

        for ($j = 0; $j < count(self::$combatRatingToItemMod); $j++)
        {
            if (!self::$combatRatingToItemMod[$j])
                continue;

            if (!($mask & (1 << $j)))
                continue;

            return self::$combatRatingToItemMod[$j];
        }

        return 0;
    }

    public static function sideByRaceMask($race)
    {
        // Any
        if (!$race || ($race & RACE_MASK_ALL) == RACE_MASK_ALL)
            return 3;

        // Horde
        if ($race & RACE_MASK_HORDE && !($race & RACE_MASK_ALLIANCE))
            return 2;

        // Alliance
        if ($race & RACE_MASK_ALLIANCE && !($race & RACE_MASK_HORDE))
            return 1;

        return 3;
    }

    // pageText for Books (Item or GO) and questText
    public static function parseHtmlText($text)
    {
        if (stristr($text, '<HTML>'))                       // text is basicly a html-document with weird linebreak-syntax
        {
            $pairs = array(
                '<HTML>'    => '',
                '</HTML>'   => '',
                '<BODY>'    => '',
                '</BODY>'   => '',
                '<BR></BR>' => '<br />',
                "\n"        => '',
                "\r"        => ''
            );

            // html may contain images
            $text = preg_replace('/"Interface\\\Pictures\\\([\w_\-]+)"/i', '"images/interface/Pictures/\1.jpg"', strtr($text, $pairs));
        }
        else
            $text = strtr($text, ["\n" => '<br />', "\r" => '']);

        // gender switch
        // ok, directed gender-reference: ($g:male:female:ref) where 'ref' is the variable (see $pairs) forward in the text determining the gender
        $text = preg_replace('/\$g\s*([^:;]+)\s*:\s*([^:;]+)\s*(:?[^:;]*);/ui', '&lt;\1/\2&gt;', $text);

        // nonesense, that the client apparently ignores
        $text = preg_replace('/\$t([^;]+);/ui', '', $text);

        // and another modifier for something russian |3-6($r) .. jesus christ <_<
        $text = preg_replace('/\|\d\-?\d?\((\$\w)\)/ui', '\1', $text);

        $pairs = array(
            '$c' => '&lt;'.Lang::$game['class'].'&gt;',
            '$C' => '&lt;'.Lang::$game['class'].'&gt;',
            '$r' => '&lt;'.Lang::$game['race'].'&gt;',
            '$R' => '&lt;'.Lang::$game['race'].'&gt;',
            '$n' => '&lt;'.Lang::$main['name'].'&gt;',
            '$N' => '&lt;'.Lang::$main['name'].'&gt;',
            '$b' => '<br />',
            '$B' => '<br />',
            '|n' => ''                                      // what .. the fuck .. another type of line terminator? (only in spanish though)
        );

        return strtr($text, $pairs);
    }

    public static function asHex($val)
    {
        $_ = decHex($val);
        while (fMod(strLen($_), 4))                         // in 4-blocks
            $_ = '0'.$_;

        return '0x'.strToUpper($_);
    }

    public static function asBin($val)
    {
        $_ = decBin($val);
        while (fMod(strLen($_), 4))                         // in 4-blocks
            $_ = '0'.$_;

        return 'b'.strToUpper($_);
    }

    public static function sqlEscape($data, $relaxed = false)
    {
        // relaxed: expecting strings for fulltext search
        $pattern = $relaxed ? ['/[;`´"\/\\\]/ui', '--'] : ['/[^\p{L}0-9\s_\-\.]/ui', '--'];

        if (!is_array($data))
            return preg_replace($pattern, '', trim($data));

        array_walk($data, function(&$item, $key) use (&$relaxed) {
            $item = self::sqlEscape($item, $relaxed);
        });

        return $data;
    }

    public static function jsEscape($string)
    {
        return strtr(trim($string), array(
            '\\' => '\\\\',
            "'"  => "\\'",
            // '"'  => '\\"',
            "\r" => '\\r',
            "\n" => '\\n',
            // '</' => '<\/',
        ));
    }

    public static function localizedString($data, $field)
    {
        $sqlLocales = ['EN', 2 => 'FR', 3 => 'DE', 6 => 'ES', 8 => 'RU'];

        // default back to enUS if localization unavailable

        // default case: selected locale available
        if (!empty($data[$field.'_loc'.User::$localeId]))
            return $data[$field.'_loc'.User::$localeId];

        // dbc-case
        else if (!empty($data[$field.$sqlLocales[User::$localeId]]))
            return $data[$field.$sqlLocales[User::$localeId]];

        // locale not enUS; aowow-type localization available; add brackets
        else if (User::$localeId != LOCALE_EN && isset($data[$field.'_loc0']) && !empty($data[$field.'_loc0']))
            return  '['.$data[$field.'_loc0'].']';

        // dbc-case
        else if (User::$localeId != LOCALE_EN && isset($data[$field.$sqlLocales[0]]) && !empty($data[$field.$sqlLocales[0]]))
            return  '['.$data[$field.$sqlLocales[0]].']';

        // locale not enUS; TC localization; add brackets
        else if (User::$localeId != LOCALE_EN && isset($data[$field]) && !empty($data[$field]))
            return '['.$data[$field].']';

        // locale enUS; TC localization; return normal
        else if (User::$localeId == LOCALE_EN && isset($data[$field]) && !empty($data[$field]))
            return $data[$field];

        // nothing to find; be empty
        else
            return '';
    }

    public static function extractURLParams($str)
    {
        $arr = explode('.', $str);

        foreach ($arr as $i => $a)
            $arr[$i] = is_numeric($a) ? (int)$a : null;

        return $arr;
    }

    // for item and spells
    public static function setRatingLevel($level, $type, $val)
    {
        if (in_array($type, [ITEM_MOD_DEFENSE_SKILL_RATING, ITEM_MOD_PARRY_RATING, ITEM_MOD_BLOCK_RATING]) && $level < 34)
            $level = 34;

        if (!isset(Util::$gtCombatRatings[$type]))
            $result = 0;
        else
        {
            if ($level > 70)
                $c = 82 / 52 * pow(131 / 63, ($level - 70) / 10);
            else if ($level > 60)
                $c = 82 / (262 - 3 * $level);
            else if ($level > 10)
                $c = ($level - 8) / 52;
            else
                $c = 2 / 52;

            $result = number_format($val / Util::$gtCombatRatings[$type] / $c, 2);
        }

        if (!in_array($type, array(ITEM_MOD_DEFENSE_SKILL_RATING, ITEM_MOD_EXPERTISE_RATING)))
            $result .= '%';

        return sprintf(Lang::$item['ratingString'], '<!--rtg%'.$type.'-->'.$result, '<!--lvl-->'.$level);
    }

    public static function powerUseLocale($domain = 'www')
    {
        foreach (Util::$localeStrings as $k => $v)
        {
            if (strstr($v, $domain))
            {
                User::useLocale($k);
                Lang::load(User::$localeString);
                return;
            }
        }

        if ($domain == 'www')
        {
            User::useLocale(LOCALE_EN);
            Lang::load(User::$localeString);
        }
    }

    // EnchantmentTypes
    // 0 => TYPE_NONE               dnd stuff; (ignore)
    // 1 => TYPE_COMBAT_SPELL       proc spell from ObjectX (amountX == procChance?; ignore)
    // 2 => TYPE_DAMAGE             +AmountX damage
    // 3 => TYPE_EQUIP_SPELL        Spells from ObjectX (amountX == procChance?)
    // 4 => TYPE_RESISTANCE         +AmountX resistance for ObjectX School
    // 5 => TYPE_STAT               +AmountX for Statistic by type of ObjectX
    // 6 => TYPE_TOTEM              Rockbiter AmountX as Damage (ignore)
    // 7 => TYPE_USE_SPELL          Engineering gadgets
    // 8 => TYPE_PRISMATIC_SOCKET   Extra Sockets AmountX as socketCount (ignore)
    public static function parseItemEnchantment($eId, $raw = false, &$name = null)
    {
        $enchant = DB::Aowow()->selectRow('SELECT *, Id AS ARRAY_KEY FROM ?_itemenchantment WHERE Id = ?d', $eId);
        if (!$enchant)
            return [];

        $name = self::localizedString($enchant, 'text');


        // parse stats
        $jsonStats = [];
        for ($h = 1; $h <= 3; $h++)
        {
            $obj = (int)$enchant['object'.$h];
            $val = (int)$enchant['amount'.$h];

            switch ($enchant['type'.$h])
            {
                case 2:
                    @$jsonStats[ITEM_MOD_WEAPON_DMG] += $val;
                    break;
                case 3:
                case 7:
                    $spl   = new SpellList(array(['s.id', $obj]));
                    if ($spl->error)
                        break;

                    $gains = $spl->getStatGain();

                    foreach ($gains as $gain)
                        foreach ($gain as $k => $v)         // array_merge screws up somehow...
                            @$jsonStats[$k] += $v;
                    break;
                case 4:
                    switch ($obj)
                    {
                        case 0:                             // Physical
                            @$jsonStats[ITEM_MOD_ARMOR] += $val;
                            break;
                        case 1:                             // Holy
                            @$jsonStats[ITEM_MOD_HOLY_RESISTANCE] += $val;
                            break;
                        case 2:                             // Fire
                            @$jsonStats[ITEM_MOD_FIRE_RESISTANCE] += $val;
                            break;
                        case 3:                             // Nature
                            @$jsonStats[ITEM_MOD_NATURE_RESISTANCE] += $val;
                            break;
                        case 4:                             // Frost
                            @$jsonStats[ITEM_MOD_FROST_RESISTANCE] += $val;
                            break;
                        case 5:                             // Shadow
                            @$jsonStats[ITEM_MOD_SHADOW_RESISTANCE] += $val;
                            break;
                        case 6:                             // Arcane
                            @$jsonStats[ITEM_MOD_ARCANE_RESISTANCE] += $val;
                            break;
                    }
                    break;
                case 5:
                    @$jsonStats[$obj] += $val;
                    break;
            }
        }

        if ($raw)
            return $jsonStats;

        // check if we use these mods
        $return = [];
        foreach ($jsonStats as $k => $v)
        {
            if ($str = Util::$itemMods[$k])
                $return[$str] = $v;
        }

        return $return;
    }

    public static function isValidPage($struct, $keys)
    {
        switch (count($keys))
        {
            case 0:
                return true;
            case 1:
                return !$keys[0] || isset($struct[$keys[0]]);
            case 2:
                if (!isset($struct[$keys[0]]))
                    return false;

                if (count($struct[$keys[0]]) == count($struct[$keys[0]], COUNT_RECURSIVE))
                    return in_array($keys[1], $struct[$keys[0]]);
                else
                    return isset($struct[$keys[0]][$keys[1]]);
            case 3:
                return isset($struct[$keys[0]][$keys[1]]) && in_array($keys[2], $struct[$keys[0]][$keys[1]]);
        }

        return false;
    }

    // default ucFirst doesn't convert UTF-8 chars
    public static function ucFirst($str)
    {
        $len   = mb_strlen($str, 'UTF-8') - 1;
        $first = mb_substr($str, 0, 1, 'UTF-8');
        $rest  = mb_substr($str, 1, $len, 'UTF-8');

        return mb_strtoupper($first, 'UTF-8').$rest;
    }

    public static function ucWords($str)
    {
        return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
    }

    public static function checkNumeric(&$data)
    {
        if ($data === null)
            return false;
        else if (!is_array($data))
        {
            $data = trim($data);

            if (is_numeric($data))
            {
                $_int   = intVal($data);
                $_float = floatVal($data);

                $data = ($_int == $_float) ? $_int : $_float;
                return true;
            }
            else if (preg_match('/^\d*,\d+$/', $data))
            {
                $data = floatVal(strtr($data, ',', '.'));
                return true;
            }

            return false;
        }

        array_walk($data, function(&$item, $key) {
            self::checkNumeric($item);
        });

        return false;                                       // always false for passed arrays
    }

    public function arraySumByKey(&$ref)
    {
        $nArgs = func_num_args();
        if (!is_array($ref) || $nArgs < 2)
            return;

        for ($i = 1; $i < $nArgs; $i++)
        {
            $arr = func_get_arg($i);
            if (!is_array($arr))
                continue;

            foreach ($arr as $k => $v)
            {
                if (!isset($ref[$k]))
                    $ref[$k] = 0;

                $ref[$k] += $v;
            }
        }
    }

    /*  from TC wiki
        fishing_loot_template           no relation     entry is linked with ID of the fishing zone or area
        creature_loot_template          entry           many <- many        creature_template       lootid
        gameobject_loot_template        entry           many <- many        gameobject_template     data1           Only GO type 3 (CHEST) or 25 (FISHINGHOLE)
        item_loot_template              entry           many <- one         item_template           entry
        disenchant_loot_template        entry           many <- many        item_template           DisenchantID
        prospecting_loot_template       entry           many <- one         item_template           entry
        milling_loot_template           entry           many <- one         item_template           entry
        pickpocketing_loot_template     entry           many <- many        creature_template       pickpocketloot
        skinning_loot_template          entry           many <- many        creature_template       skinloot        Can also store minable/herbable items gathered from creatures
        quest_mail_loot_template        entry                               quest_template          RewMailTemplateId
        reference_loot_template         entry           many <- many        _loot_template          -mincountOrRef  In case of negative mincountOrRef
    */
    private static function getLootByEntry($tableName, $lootId, $groupId = 0, $baseChance = 1.0)
    {
        $loot     = [];
        $rawItems = [];

        if (!$tableName || !$lootId)
            return null;

        $rows = DB::Aowow()->select('SELECT * FROM ?# WHERE entry = ?d{ AND groupid = ?d}', $tableName, abs($lootId), $groupId ? $groupId : DBSIMPLE_SKIP);
        if (!$rows)
            return null;

        $groupChances = [];
        $nGroupEquals = [];
        foreach ($rows as $entry)
        {
            $set = array(
                'quest'         => $entry['ChanceOrQuestChance'] < 0,
                'group'         => $entry['groupid'],
                'reference'     => $lootId < 0 ? abs($lootId) : 0,
                'realChanceMod' => $baseChance
            );

            if ($entry['lootmode'] > 1)
            {
                $buff = [];
                for ($i = 0; $i < 8; $i++)
                    if ($entry['lootmode'] & (1 << $i))
                        $buff[] = $i;

                $set['mode'] = implode(', ', $buff);
            }
            else
                $set['mode'] = 0;

            /*
                modes:{"mode":8,"4":{"count":7173,"outof":17619},"8":{"count":7173,"outof":10684}}
                ignore lootmodes from sharedDefines.h use different creatures/GOs from each template
                modes.mode = b6543210
                              ||||||'dungeon heroic
                              |||||'dungeon normal
                              ||||'<empty>
                              |||'10man normal
                              ||'25man normal
                              |'10man heroic
                              '25man heroic
            */

            if ($entry['mincountOrRef'] < 0)
            {
                list($data, $raw) = self::getLootByEntry(LOOT_REFERENCE, $entry['mincountOrRef'], $entry['groupid'], abs($entry['ChanceOrQuestChance'] / 100));

                $loot     = array_merge($loot, $data);
                $rawItems = array_merge($rawItems, $raw);

                $set['content']    = $entry['mincountOrRef'];
                $set['multiplier'] = $entry['maxcount'];
            }
            else
            {
                $rawItems[]     = $entry['item'];
                $set['content'] = $entry['item'];
                $set['min']     = $entry['mincountOrRef'];
                $set['max']     = $entry['maxcount'];
            }

            if ($set['quest'] || !$set['group'])
                $set['groupChance'] = abs($entry['ChanceOrQuestChance']);
            else if ($entry['groupid'] && !$entry['ChanceOrQuestChance'])
            {
                @$nGroupEquals[$entry['groupid']]++;
                $set['groupChance'] = &$groupChances[$entry['groupid']];
            }
            else if ($entry['groupid'] && $entry['ChanceOrQuestChance'])
            {
                @$groupChances[$entry['groupid']] += $entry['ChanceOrQuestChance'];
                $set['groupChance'] = abs($entry['ChanceOrQuestChance']);
            }
            else                                            // shouldn't happened
            {
                if (User::isInGroup(U_GROUP_DEV))
                    die(var_dump($entry));
                else
                    continue;
            }

            $loot[] = $set;
        }

        foreach (array_keys($nGroupEquals) as $k)
        {
            $sum = $groupChances[$k];
            if (!$sum)
                $sum = 0;
            else if ($sum > 100)                            // group has > 100% dropchance .. hmm, display some kind of error..?
                $sum = 100;

            $cnt = empty($nGroupEquals[$k]) ? 1 : $nGroupEquals[$k];

            $groupChances[$k] = (100 - $sum) / $cnt;        // is applied as backReference to items with 0-chance
        }

        return [$loot, array_unique($rawItems)];
    }

    //                                                      v this is bullshit, but as long as there is no integral template class..
    public static function handleLoot($table, $entry, &$pageTemplate, $debug = false)
    {
        $lv    = [];
        $loot  = null;

        if (!$table || !$entry)
            return null;

        /*
            todo (high): implement conditions on loot (and conditions in general)

        also

            // if (is_array($entry) && in_array($table, [LOOT_CREATURE, LOOT_GAMEOBJECT])
                // iterate over the 4 available difficulties and assign modes
        */

        $struct = self::getLootByEntry($table, $entry);
        if (!$struct)
            return $lv;

        $items = new ItemList(array(['i.id', $struct[1]]));
        $items->addGlobalsToJscript($pageTemplate, GLOBALINFO_SELF | GLOBALINFO_RELATED);
        $foo = $items->getListviewData();

        // assign listview LV rows to loot rows, not the other way round! The same item may be contained multiple times
        foreach ($struct[0] as $loot)
        {
            $base = array(
                'percent' => round($loot['groupChance'] * $loot['realChanceMod'], 3),
                'group'   => $loot['group'],
                'count'   => 1                              // satisfies the sort-script
            );

            if ($_ = $loot['mode'])
                $base['mode'] = $_;

            if ($_ = $loot['reference'])
                $base['reference'] = $_;

            $stack = [];                                    // equal distribution between min/max .. not blizzlike, but hey, TC-issue
            if (isset($loot['max']) && isset($loot['min']) && ($loot['max'] > $loot['min']))
                for ($i = $loot['min']; $i <= $loot['max']; $i++)
                    $stack[$i] = round(100 / (1 + $loot['max'] - $loot['min']), 3);

            if ($stack)                                     // yes, it wants a string .. how weired is that..
                $base['pctstack'] = json_encode($stack, JSON_NUMERIC_CHECK);

            if ($loot['content'] > 0)                       // regular drop
            {
                if (!$debug)
                {
                    if (!isset($lv[$loot['content']]))
                        $lv[$loot['content']] = array_merge($foo[$loot['content']], $base, ['stack' => [$loot['min'], $loot['max']]]);
                    else
                        $lv[$loot['content']]['percent'] += $base['percent'];
                }
                else
                    $lv[] = array_merge($foo[$loot['content']], $base, ['stack' => [$loot['min'], $loot['max']]]);
            }
            else if ($debug)                                // create dummy for ref-drop
            {
                $data = array(
                    'id'    => $loot['content'],
                    'name'  => '@REFERENCE: '.abs($loot['content']),
                    'icon'  => 'trade_engineering',
                    'stack' => [$loot['multiplier'], $loot['multiplier']]
                );
                $lv[] = array_merge($base, $data);

                $pageTemplate->extendGlobalData(TYPE_ITEM, [$loot['content'] => $data]);
            }
        }

        // move excessive % to extra loot
        if (!$debug)
        {
            foreach ($lv as &$_)
            {
                if ($_['percent'] <= 100)
                    continue;

                while ($_['percent'] > 200)
                {
                    $_['stack'][0]++;
                    $_['stack'][1]++;
                    $_['percent'] -= 100;
                }

                $_['stack'][1]++;
                $_['percent'] = 100;
            }
        }

        return $lv;
    }

    public static function getLootSource($itemId)
    {
        if (!$itemId)
            return [];

        $final      = [];
        $refResults = [];
        $chanceMods = [];
        $query      =   'SELECT
                           -lt1.entry AS ARRAY_KEY,
                            IF (lt1.mincountOrRef > 0, lt1.item, lt1.mincountOrRef) AS item,
                            lt1.ChanceOrQuestChance AS chance,
                            SUM(IF(lt2.ChanceOrQuestChance = 0, 1, 0)) AS nZeroItems,
                            SUM(IF(lt2.ChanceOrQuestChance > 0, lt2.ChanceOrQuestChance, 0)) AS sumChance,
                            IF(lt1.groupid > 0, 1, 0) AS isGrouped,
                            IF (lt1.mincountOrRef > 0, lt1.mincountOrRef, 1) AS min,
                            IF (lt1.mincountOrRef > 0, lt1.maxcount, 1) AS max,
                            IF (lt1.mincountOrRef < 0, lt1.maxcount, 1) AS multiplier
                        FROM
                            ?# lt1
                        LEFT JOIN
                            ?# lt2 ON lt1.entry = lt2.entry AND lt1.groupid = lt2.groupid
                        WHERE
                            %s
                        GROUP BY lt2.entry';

        $calcChance = function ($refs, $parents = []) use (&$chanceMods)
        {
            $return = [];

            foreach ($refs as $rId => $ref)
            {
                // errör: item/ref is in group 0 without a chance set
                if (!$ref['chance'] && !$ref['isGrouped'])
                    continue;                               // todo (low): create dubug output

                // errör: item/ref is in group with >100% chance across all items contained
                if ($ref['isGrouped'] && $ref['sumChance'] > 100)
                    continue;                               // todo (low): create dubug output

                $chance = ($ref['chance'] ? $ref['chance'] : (100 - $ref['sumChance']) / $ref['nZeroItems']) / 100;

                // apply inherited chanceMods
                if (isset($chanceMods[$ref['item']]))
                {
                    $chance *= $chanceMods[$ref['item']][0];
                    $chance  = 1 - pow(1 - $chance, $chanceMods[$ref['item']][1]);
                }

                // save chance for parent-ref
                $chanceMods[$rId] = [$chance, $ref['multiplier']];

                // refTemplate doesn't point to a new ref -> we are done
                if (!in_array($rId, $parents))
                {
                    $data = array(
                        'percent' => $chance,
                        'stack'   => [$ref['multiplier'], $ref['multiplier']],
                        'count'   => 1                          // ..and one for the sort script
                    );

                    $stack = [];                                // equal distribution between min/max .. not blizzlike, but hey, TC-issue
                    if ($ref['max'] > $ref['min'])
                        for ($i = $ref['min']; $i <= $ref['max']; $i++)
                            $stack[$i] = round(100 / (1 + $ref['max'] - $ref['min']), 3);

                    if ($stack)                                 // yes, it wants a string .. how weired is that..
                        $data['pctstack'] = json_encode($stack, JSON_NUMERIC_CHECK);

                    $return[$rId] = $data;
                }
            }

            return $return;
        };

        $newRefs = DB::Aowow()->select(
            sprintf($query, 'lt1.item = ?d AND lt1.mincountOrRef > 0'),
            LOOT_REFERENCE, LOOT_REFERENCE,
            $itemId
        );

        while ($newRefs)
        {
            $curRefs = $newRefs;
            $newRefs = DB::Aowow()->select(
                sprintf($query, 'lt1.mincountOrRef IN (?a)'),
                LOOT_REFERENCE, LOOT_REFERENCE,
                array_keys($curRefs)
            );

            $refResults += $calcChance($curRefs, array_column($newRefs, 'item'));
        }

        for ($i = 1; $i < count(self::$lootTemplates); $i++)
        {
            $res = DB::Aowow()->select(
                sprintf($query, '{lt1.mincountOrRef IN (?a) OR }(lt1.mincountOrRef > 0 AND lt1.item = ?d)'),
                self::$lootTemplates[$i], self::$lootTemplates[$i],
                $refResults ? array_keys($refResults) : DBSIMPLE_SKIP,
                $itemId
            );

            if ($_ = $calcChance($res))
            {
                // format for use in item.php
                $sort = [];
                foreach ($_ as $k => $v)
                {
                    unset($_[$k]);
                    $v['percent'] = round($v['percent'] * 100, 3);
                    $v['key'] = abs($k);                    // array_multisort issue: it does not preserve numeric keys, restore after sort
                    $sort[$k] = $v['percent'];
                    $_[abs($k)] = $v;
                }

                array_multisort($sort, SORT_DESC, $_);

                foreach ($_ as $k => $v)
                {
                    $key = $v['key'];
                    unset($_[$k]);
                    unset($v['key']);

                    $_[$key] = $v;
                }

                $final[self::$lootTemplates[$i]] = $_;
            }
        }

        return $final;
    }
}

?>
