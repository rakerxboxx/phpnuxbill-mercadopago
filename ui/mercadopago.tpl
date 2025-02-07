{include file="sections/header.tpl"}

<form class="form-horizontal" method="post" role="form" action="{$_url}paymentgateway/mercadopago" >
    <div class="row">
        <div class="col-sm-12 col-md-12">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">
                    <div class="panel-title">Mercado Pago Settings</div>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-md-2 control-label">Access Token</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="access_token" name="access_token" placeholder="xxxxxxxxxxxxxxxxx" value="{$_c['mercadopago_access_token']}" required>
                            <small class="form-text text-muted">Login to <a href="https://www.mercadopago.com.br/developers/panel" target="_blank">Mercado Pago Developer Panel</a> to get your access token.</small>
                        </div>
                    </div>
                </div>
                <div class="panel-footer">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </div>
        </div>
    </div>
</form>

{include file="sections/footer.tpl"}
