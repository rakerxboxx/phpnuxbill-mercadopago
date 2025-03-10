{include file="sections/header.tpl"}

<form class="form-horizontal" method="post" role="form" action="{$_url}paymentgateway/mercadopago" >
    <div class="row">
        <div class="col-sm-12 col-md-12">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">
                    <div class="panel-title">Mercado Pago PIX Settings</div>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-md-2 control-label">Access Token</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="access_token" name="access_token" placeholder="TEST-0000000000000000-000000-00000000000000000000000000000000-000000000" value="{$_c['mercadopago_access_token']}" required>
                            <small class="form-text text-muted">Login to <a href="https://www.mercadopago.com.br/developers/panel" target="_blank">Mercado Pago Developer Dashboard</a> to get your access token</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">PIX Key (Optional)</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="pix_key" name="pix_key" placeholder="Your PIX key (CPF, CNPJ, Email, Phone or Random)" value="{$_c['mercadopago_pix_key']}">
                            <small class="form-text text-muted">Your PIX key registered with Mercado Pago (for reference only)</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="col-md-2 control-label">Sandbox Mode</label>
                        <div class="col-md-6">
                            <select class="form-control" name="sandbox_mode">
                                <option value="1" {if $_c['mercadopago_sandbox_mode'] == '1'}selected{/if}>Yes (Testing)</option>
                                <option value="0" {if $_c['mercadopago_sandbox_mode'] == '0'}selected{/if}>No (Production)</option>
                            </select>
                            <small class="form-text text-muted">Set to Yes for testing, No for live transactions</small>
                        </div>
                    </div>
                    
                     <div class="form-group">
                        <label class="col-md-2 control-label">Webhook URL</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="webhook" value="" readonly>
                            <small class="form-text text-muted">Add this URL in your Mercado Pago webhook settings</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-primary waves-effect waves-light" type="submit">SAVE CHANGES</button>
                        </div>
                    </div>
                        <pre>/ip hotspot walled-garden
                   add dst-host=mercadopago.com
                   add dst-host=*.mercadopago.com
                   add dst-host=app.spaconett.com</pre>
                </div>
            </div>

        </div>
    </div>
</form>

<script>
let input = document.getElementById('webhook');
var fullURL = window.location.href;
input.value = "https://"+fullURL.split('/')[2]+"/index.php?_route=callback/mercadopago";
</script>
{include file="sections/footer.tpl"}

