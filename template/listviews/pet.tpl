var _ = function(family)
{ldelim}
	family.foodCount = 0;
	for(var food in g_pet_foods)
	{ldelim}
		if(family.diet & food)
			family.foodCount++;
	{rdelim}

	family.spellCount = 0;

	for(var i = 0, len = family.spells.length; i < len; ++i)
	{ldelim}
		if(family.spells[i])
			family.spellCount++;
	{rdelim}
{rdelim};
{strip}
    new Listview({ldelim}
        template:'pet',
        computeDataFunc: _,
        {if !isset($params.id)}id:'hunter-pets',{/if}
        {if !isset($params.name)}name:LANG.tab_pets,{/if}
        {if !isset($params.parent)}parent:'lv-generic',{/if}
        {foreach from=$params key=k item=v}
            {if $v[0] == '$'}
                {$k}:{$v|substr:1},
            {else if $v}
                {$k}:'{$v}',
            {/if}
        {/foreach}
        data:[
            {foreach name=i from=$data item=curr}
                {ldelim}
                    {foreach from=$curr  key='name' item=val}
                        {if $name != 'id'}
                            {$name}:{$val|@json_encode:$smarty.const.JSON_NUMERIC_CHECK},
                        {/if}
                    {/foreach}
                    id:{$curr.id}
                {rdelim}
                {if $smarty.foreach.i.last}{else},{/if}
            {/foreach}
        ]
    {rdelim});
{/strip}
