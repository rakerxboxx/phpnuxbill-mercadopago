<div class="form-group">
    <label for="mercadopago_client_id">Client ID</label>
    <input type="text" class="form-control" id="mercadopago_client_id" name="mercadopago_client_id" value="{$config.mercadopago_client_id}">
</div>

<div class="form-group">
    <label for="mercadopago_client_secret">Client Secret</label>
    <input type="text" class="form-control" id="mercadopago_client_secret" name="mercadopago_client_secret" value="{$config.mercadopago_client_secret}">
</div>

<div class="form-group">
    <label for="mercadopago_currency">Currency</label>
    <select class="form-control" id="mercadopago_currency" name="mercadopago_currency">
        {foreach from=$currencies item=currency}
            <option value="{$currency.id}" {if $config.mercadopago_currency == $currency.id}selected{/if}>{$currency.name}</option>
        {/foreach}
    </select>
</div>
