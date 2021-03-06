<?php

if (!defined('AOWOW_REVISION'))
    die('illegal access');


class CharClassList extends BaseType
{
    public static $type      = TYPE_CLASS;
    public static $brickFile = 'class';

    protected     $queryBase = 'SELECT *, id AS ARRAY_KEY FROM ?_classes c';

    public function __construct($conditions = [])
    {
        parent::__construct($conditions);

        foreach ($this->iterate() as $k => &$_curTpl)
            $_curTpl['skills'] = explode(' ', $_curTpl['skills']);
    }

    public function getListviewData()
    {
        $data = [];

        foreach ($this->iterate() as $__)
        {
            $data[$this->id] = array(
                'id'     => $this->id,
                'name'   => $this->getField('name', true),
                'races'  => $this->curTpl['raceMask'],
                'roles'  => $this->curTpl['roles'],
                'weapon' => $this->curTpl['weaponTypeMask'],
                'armor'  => $this->curTpl['armorTypeMask'],
                'power'  => $this->curTpl['powerType'],
            );

            if ($this->curTpl['flags'] & 0x40)
                $data[$this->id]['hero'] = 1;

            if ($this->curTpl['expansion'])
                $data[$this->id]['expansion'] = $this->curTpl['expansion'];
        }

        return $data;
    }

    public function addGlobalsToJScript($addMask = 0)
    {
        foreach ($this->iterate() as $__)
            Util::$pageTemplate->extendGlobalData(self::$type, [$this->id => ['name' => $this->getField('name', true)]]);
    }

    public function addRewardsToJScript(&$ref) { }
    public function renderTooltip() { }
}

?>
