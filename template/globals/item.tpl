var _ = g_items;
{strip}
{foreach from=$data key=id item=item}
    _[{$id}]={ldelim}
        name_{$user.language}:'{$item.name|escape:"javascript"}',
        icon:'{$item.icon|escape:"javascript"}'
        {if isset($item.quality)}, quality:'{$item.quality}'{/if}
    {rdelim};
{/foreach}

{if $extra}
    _[{$extra.id}].tooltip_{$user.language} = '{$extra.tooltip}';
    _[{$extra.id}].spells_{$user.language}  = {$extra.spells|@json_encode:$smarty.const.JSON_NUMERIC_CHECK};
{/if}

{/strip}
