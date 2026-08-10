<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

$order = wc_get_order($order_id);
if (!$order || $order->get_payment_method() !== $this->id || !isset($_GET['pix-payment']) || $_GET['pix-payment'] !== '1') {
    return;
}

$pix_data = $order->get_meta('paypal_brasil_pix_data');
if (empty($pix_data) || !isset($pix_data['payment_source']['pix'])) {
    return;
}
$orderPaymentPage    = $order->get_checkout_payment_url();
$pix                 = $pix_data['payment_source']['pix']['qr_details'];
$qrCodeDataUri       = $this->gerar_qr_code_pix_data_uri($pix['qr_payload']);
$pix_expiry          = $order->get_meta('paypal_brasil_pix_expiry');
$expiry_timestamp    = $pix_expiry ? intval($pix_expiry) : (time() + 1800);
$pix_logo_url        = plugins_url('assets/images/logo-pix.svg', PAYPAL_PAYMENTS_MAIN_FILE);

if ($order->needs_payment() || $order->needs_processing()) {
?>
<button type="button" class="pix-open-button" aria-label="<?php esc_attr_e('Abrir', 'paypal-brasil-para-woocommerce'); ?>">
    <?php _e('Ver QR code', 'paypal-brasil-para-woocommerce'); ?>
</button>

<!-- Modal PIX -->
<div class="pix-modal-backdrop" id="pix-payment-modal">
  <div class="pix-modal">
  <button type="button" class="pix-close-button" aria-label="<?php esc_attr_e('Fechar', 'paypal-brasil-para-woocommerce'); ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    <!-- Estado: QR / aguardando -->
    <div id="pix-qrcode-modal-content">
  

      <div class="pix-modal-title"><?php _e('Pague com PIX', 'paypal-brasil-para-woocommerce'); ?></div>
      <div class="pix-modal-sub"><?php _e('Escaneie o QR code com o app do seu banco', 'paypal-brasil-para-woocommerce'); ?></div>

      <div class="pix-qr-wrap">
        <div class="pix-qr-frame">
          <img src="<?php echo esc_attr($qrCodeDataUri); ?>" alt="QR Code PIX" class="pix-qr-img" />
        </div>
      </div>

      <div class="pix-timer-row" id="pix-timer-row">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        <?php _e('QR code expira em', 'paypal-brasil-para-woocommerce'); ?>&nbsp;<span class="pix-timer-count" id="pix-timer">--:--</span>
      </div>

      <div class="pix-copy-row">
        <span class="pix-copy-key" id="pix-code"><?php echo esc_html($pix['qr_payload']); ?></span>
        <button id="pix-code-button" class="pix-copy-btn"><?php _e('Copiar', 'paypal-brasil-para-woocommerce'); ?></button>
      </div>

      <p class="pix-waiting"><?php _e('Aguardando pagamento…', 'paypal-brasil-para-woocommerce'); ?></p>
    </div>

    <!-- Estado: pagamento confirmado -->
    <div id="div-payment-confirmed">
      <div class="pix-paid-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
      </div>
      <div class="pix-paid-title"><?php _e('Pagamento confirmado!', 'paypal-brasil-para-woocommerce'); ?></div>
      <div class="pix-paid-sub"><?php _e('Seu pagamento foi aprovado. A loja já está preparando o seu pedido.', 'paypal-brasil-para-woocommerce'); ?></div>
      <a href="<?php echo esc_url($order->get_checkout_order_received_url()); ?>" class="pix-view-order-btn">
        <?php _e('Ver meu pedido', 'paypal-brasil-para-woocommerce'); ?>
      </a>
    </div>

    <!-- Estado: expirado / processando -->
    <div id="div-payment-expired">
      <div class="pix-confirmed-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
      </div>
      <div class="pix-confirmed-title"><?php _e('Pedido recebido!', 'paypal-brasil-para-woocommerce'); ?></div>
      <div class="pix-confirmed-sub"><?php _e('O pagamento está sendo processado. Você será notificado assim que for confirmado.', 'paypal-brasil-para-woocommerce'); ?></div>
    </div>

  </div>
</div>
<?php } ?>

<script type="text/javascript">
window.pixData = {
    order_id        : '<?php echo esc_js($order_id); ?>',
    expiry_timestamp: <?php echo intval($expiry_timestamp); ?>,
    nonce           : '<?php echo wp_create_nonce('pix_status_check '); ?>',
    status_check_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    success_url     : '<?php echo esc_url($order->get_checkout_order_received_url()); ?>'
};

(function () {
    var modal    = document.getElementById('pix-payment-modal');
    var closeBtn = document.querySelector('.pix-close-button');
    var openBtn  = document.querySelector('.pix-open-button');

    function openModal()  { modal && modal.classList.add('open'); }
    function closeModal() { modal && modal.classList.remove('open'); }

    document.addEventListener('DOMContentLoaded', function () {
        openModal();

        if (closeBtn) closeBtn.addEventListener('click', closeModal);

        if (openBtn) {
            openBtn.addEventListener('click', function () {
                openModal();
                startPixPolling();
            });
        }

        // Botão copiar
        var copyBtn = document.getElementById('pix-code-button');
        var copyKey = document.getElementById('pix-code');
        if (copyBtn && copyKey) {
            copyBtn.addEventListener('click', function () {
                var text = copyKey.textContent.trim();
                var restore = function () {
                    copyBtn.textContent = '<?php echo esc_js(__('Copiar', 'paypal-brasil-para-woocommerce')); ?>';
                    copyBtn.style.borderColor = '';
                    copyBtn.style.color = '';
                };
                var onCopied = function () {
                    copyBtn.textContent = '<?php echo esc_js(__('Copiado!', 'paypal-brasil-para-woocommerce')); ?>';
                    copyBtn.style.borderColor = 'var(--pix-green)';
                    copyBtn.style.color = 'var(--pix-green)';
                    setTimeout(restore, 2000);
                };
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(onCopied).catch(function () {
                        fallbackCopy(text);
                        onCopied();
                    });
                } else {
                    fallbackCopy(text);
                    onCopied();
                }
            });
        }

        startPixTimer();
        startPixPolling();
    });

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }
}());

// ── Timer ─────────────────────────────────────────────
function startPixTimer() {
    var el  = document.getElementById('pix-timer');
    var row = document.getElementById('pix-timer-row');
    if (!el) return;

    (function tick() {
        var remaining = Math.max(0, window.pixData.expiry_timestamp - Math.floor(Date.now() / 1000));
        var m = String(Math.floor(remaining / 60)).padStart(2, '0');
        var s = String(remaining % 60).padStart(2, '0');
        el.textContent = m + ':' + s;

        // Urgência: vermelho nos últimos 5 minutos
        if (row) {
            if (remaining > 0 && remaining <= 300) {
                row.classList.add('pix-timer-urgent');
            } else {
                row.classList.remove('pix-timer-urgent');
            }
        }

        if (remaining > 0) {
            setTimeout(tick, 1000);
        } else {
            // Timer esgotado — esconde a linha de timer
            if (row) row.style.display = 'none';
        }
    }());
}

// ── Polling ───────────────────────────────────────────
var pixTimer    = null;
var pixInterval = null;

function hideQrContent() {
    var content = document.getElementById('pix-qrcode-modal-content');
    if (content) content.style.display = 'none';
}

function showPaymentConfirmed() {
    stopPixPolling();
    hideQrContent();
    var confirmed = document.getElementById('div-payment-confirmed');
    if (confirmed) confirmed.style.display = 'flex';
}

function showPaymentExpired() {
    hideQrContent();
    var expired = document.getElementById('div-payment-expired');
    if (expired) expired.style.display = 'flex';
}

function startPixPolling() {
    stopPixPolling();
    var attempts    = 0;
    var maxAttempts = 10;

    pixTimer = setTimeout(function () {
        checkPixPaymentStatus();
        attempts++;

        pixInterval = setInterval(function () {
            attempts++;
            checkPixPaymentStatus();
            if (attempts >= maxAttempts) {
                stopPixPolling();
                showPaymentExpired();
            }
        }, 10000);
    }, 10000);
}

function stopPixPolling() {
    clearTimeout(pixTimer);
    clearInterval(pixInterval);
    pixTimer = pixInterval = null;
}

function checkPixPaymentStatus() {
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method : 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body   : 'action=paypal_check_pix_payment_status&order_id=<?php echo $order->get_id(); ?>&nonce=<?php echo wp_create_nonce('paypal_check_pix_payment_status'); ?>'
    })
    .then(function (res) { return res.text(); })
    .then(function (text) {
        var data;
        try { data = JSON.parse(text); } catch (e) { return; }
        var status = data && data.data && data.data.status;
        if (!status) return;
        if (data.success && status === 'COMPLETED') {
            showPaymentConfirmed();
            setTimeout(function () {
                window.location.href = '<?php echo $order->get_checkout_order_received_url(); ?>';
            }, 3000);
        } else if (!data.success && status === 'EXPIRED') {
            stopPixPolling();
            showPaymentExpired();
        }
    })
    .catch(function (err) { console.error('Erro ao verificar pagamento:', err); });
}
</script>

<style>
:root {
  --pix-green      : #00a86b;
  --pix-green-light: #e6f7f1;
  --pix-green-dark : #007a4d;
  --pix-gray-100   : #f5f5f5;
  --pix-gray-200   : #e8e8e8;
  --pix-gray-400   : #aaa;
  --pix-gray-600   : #666;
  --pix-gray-800   : #222;
  --pix-radius     : 12px;
}

/* ── Botão abrir ── */
.pix-open-button {
  margin-bottom: 1em;
  padding: 11px 22px;
  /* background: var(--pix-green); */
  /* color: #fff; */
  border: none;
  /* border-radius: var(--pix-radius); */
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  transition: background .15s;
}
.pix-open-button:hover { /* background: var(--pix-green-dark); */   }

/* ── Backdrop ── */
.pix-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, .45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 24px 16px;
  opacity: 0;
  pointer-events: none;
  transition: opacity .25s;
}
.pix-modal-backdrop.open {
  opacity: 1;
  pointer-events: all;
}

/* ── Modal card ── */
.pix-modal {
  background: #fff;
  width: 100%;
  max-width: 400px;
  max-height: calc(100vh - 48px);
  overflow-y: auto;
  border-radius: 5px;
  padding: 32px 24px 36px;
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
  transform: translateY(14px) scale(.98);
  transition: transform .3s cubic-bezier(.4, 0, .2, 1);
}
.pix-modal-backdrop.open .pix-modal {
  transform: translateY(0) scale(1);
}

/* ── Botão fechar ── */
.pix-close-button {
  position: absolute;
  top: 14px;
  right: 14px;
  background: none;
  border: none;
  padding: 4px;
  color: var(--pix-gray-400);
  cursor: pointer;
  line-height: 0;
  transition: color .15s;
  border-radius: 6px;
}
.pix-close-button:hover { color: var(--pix-gray-800); }

/* ── Logo ── */
.pix-brand-logo {
  height: 26px;
  margin-bottom: 14px;
}

/* ── Títulos ── */
.pix-modal-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--pix-gray-800);
  margin-bottom: 4px;
  text-align: center;
}
.pix-modal-sub {
  font-size: 12px;
  color: var(--pix-gray-600);
  margin-bottom: 20px;
  text-align: center;
  line-height: 1.4;
}

/* ── QR code ── */
.pix-qr-wrap { margin-bottom: 14px; }
.pix-qr-frame {
  width: 182px;
  height: 182px;
  border-radius: 12px;
  padding: 12px;
  box-shadow: 0 0 0 1.5px var(--pix-gray-200);
  display: grid;
  place-items: center;
}
.pix-qr-img {
  width: 100%;
  height: 100%;
  display: block;
  border-radius: 4px;
}

/* ── Timer ── */
.pix-timer-row {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--pix-gray-600);
  margin-bottom: 16px;
  transition: color .3s;
}
.pix-timer-count {
  font-weight: 700;
  color: var(--pix-gray-800);
  font-variant-numeric: tabular-nums;
  transition: color .3s;
}
.pix-timer-urgent {
  color: #e53e3e;
}
.pix-timer-urgent .pix-timer-count {
  color: #e53e3e;
  animation: pix-timer-blink 1s step-start infinite;
}
@keyframes pix-timer-blink {
  0%, 100% { opacity: 1; }
  50%       { opacity: .4; }
}

/* ── Linha de cópia ── */
.pix-copy-row {
  width: 100%;
  background: var(--pix-gray-100);
  border-radius: 10px;
  padding: 10px 12px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  margin-bottom: 14px;
}
.pix-copy-key {
  font-size: 11px;
  color: var(--pix-gray-600);
  font-family: monospace;
  line-height: 1.4;
  word-break: break-all;
  max-height: 3.2em;
  overflow: hidden;
}
.pix-copy-btn {
  background: none;
  border: 1.5px solid var(--pix-gray-200);
  border-radius: 8px;
  padding: 5px 12px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  flex-shrink: 0;
  transition: background .12s;
}
.pix-copy-btn:hover { background: var(--pix-gray-200); }

/* ── Aguardando ── */
.pix-waiting {
  font-size: 12px;
  color: var(--pix-gray-400);
  margin: 0 !important;
}

/* ── Container QR ── */
#pix-qrcode-modal-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
}

/* ── Estado pagamento confirmado ── */
#div-payment-confirmed {
  display: none;
  flex-direction: column;
  align-items: center;
  width: 100%;
  text-align: center;
}
.pix-paid-icon {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--pix-green-light);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
  animation: pix-pop .4s cubic-bezier(.4, 0, .2, 1);
}
.pix-paid-icon svg {
  width: 28px;
  height: 28px;
  stroke: var(--pix-green);
  fill: none;
  stroke-width: 2.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.pix-paid-title {
  font-size: 17px;
  font-weight: 700;
  color: var(--pix-gray-800);
  margin-bottom: 8px;
}
.pix-paid-sub {
  font-size: 13px;
  color: var(--pix-gray-600);
  line-height: 1.5;
  max-width: 280px;
  margin-bottom: 24px;
}
.pix-view-order-btn {
  display: block;
  width: 100%;
  padding: 14px;
  background: var(--pix-green);
  color: #fff;
  border: none;
  border-radius: var(--pix-radius);
  font-size: 14px;
  font-weight: 700;
  text-align: center;
  text-decoration: none;
  cursor: pointer;
  transition: background .15s;
}
.pix-view-order-btn:hover {
  background: var(--pix-green-dark);
  color: #fff;
  text-decoration: none;
}

/* ── Estado expirado / processando ── */
#div-payment-expired {
  display: none;
  flex-direction: column;
  align-items: center;
  width: 100%;
  text-align: center;
}
.pix-confirmed-icon {
  width: 60px;
  height: 60px;
  border-radius: 50%;
  background: var(--pix-green-light);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
  animation: pix-pop .35s cubic-bezier(.4, 0, .2, 1);
}
.pix-confirmed-icon svg {
  width: 26px;
  height: 26px;
  stroke: var(--pix-green);
  fill: none;
  stroke-width: 2.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}
@keyframes pix-pop {
  0%   { transform: scale(.6); opacity: 0; }
  70%  { transform: scale(1.1); }
  100% { transform: scale(1); opacity: 1; }
}
.pix-confirmed-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--pix-gray-800);
  margin-bottom: 8px;
}
.pix-confirmed-sub {
  font-size: 13px;
  color: var(--pix-gray-600);
  line-height: 1.5;
  max-width: 280px;
}

@media (max-width: 480px) {
  .pix-modal { padding: 24px 18px 30px; }
  .pix-qr-frame { width: 156px; height: 156px; }
}
</style>
