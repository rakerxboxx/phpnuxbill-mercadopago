{include file="customer/header.tpl"}

<div class="row">
    <div class="col-sm-12 col-md-12">
        <div class="panel panel-primary panel-hovered panel-stacked mb30">
            <div class="panel-heading">
                <div class="panel-title">Pagamento via PIX</div>
            </div>
            <div class="panel-body">
                <div class="alert alert-info">
                    <strong>3Detalhes do Pagamento</strong><br>
                    Plano: {$trx['plan_name']}<br>
                    Valor: {$_c['currency_code']} {$trx['price']}<br>
                    ID da Transação: #{$trx_id}<br>
                    Expira em: {$expiration_date}
                </div>
                
                {if isset($payment_status) && $payment_status != "pending"}
                    <div class="alert alert-{if $payment_status == "approved"}success{elseif $payment_status == "processing"}warning{else}danger{/if}">
                        <strong>Status do Pagamento: {$payment_status|capitalize}</strong><br>
                        {$payment_message}
                    </div>
                {/if}
                
                <div class="row">
                    <div class="col-md-6 text-center">
                        <h4>Escaneie o QR Code PIX</h4>
                        <div class="qr-code-container" style="margin: 20px auto; max-width: 250px;">
                            <img src="data:image/png;base64,{$qr_code_base64}" alt="QR Code PIX" class="img-responsive center-block">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h4>Ou copie o código PIX</h4>
                        <div class="well">
                            <p>Código PIX Copia e Cola:</p>
                            <div class="input-group">
                                <input type="text" class="form-control" id="pix-code" value="{$qr_code}" readonly>
                                <span class="input-group-btn">
                                    <button class="btn btn-primary" type="button" onclick="copyPixCode()">Copiar</button>
                                </span>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <strong>Instruções:</strong>
                            <ol>
                                <li>Abra o aplicativo do seu banco</li>
                                <li>Escolha a opção PIX</li>
                                <li>Escaneie o QR Code ou cole o código PIX</li>
                                <li>Confirme as informações e finalize o pagamento</li>
                                <li>Após o pagamento, seu plano será ativado automaticamente</li>
                            </ol>
                        </div>
                    </div>
                </div>
                
                <div class="text-center" style="margin-top: 20px;">
                    <a href="{$_url}order/view/{$trx_id}" class="btn btn-info">Verificar Status do Pagamento</a>
                    <a href="{$_url}order/package" class="btn btn-default">Cancelar</a>
                </div>
                
                <div class="text-center" style="margin-top: 20px;">
                    <p>Após realizar o pagamento, seu plano será ativado automaticamente em alguns instantes.</p>
                    <p>Se você já pagou e seu plano não foi ativado, clique em "Verificar Status do Pagamento".</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyPixCode() {
    var copyText = document.getElementById("pix-code");
    copyText.select();
    document.execCommand("copy");
    alert("Código PIX copiado!");
}

// Auto-refresh to check payment status
setTimeout(function() {
    window.location.href = "{$_url}order/view/{$trx_id}";
}, 30000); // Refresh after 1 minute
</script>

{include file="customer/footer.tpl"}

