/*! For license information please see frontend-shortcut.js.LICENSE.txt */
(()=>{"use strict";var t={"./src/frontend/frontend-sdk/frontend-sdk-spb.ts":(t,e,a)=>{a.r(e),a.d(e,{importSpbSdk:()=>n});var r=function(t,e,a,r){return new(a||(a=Promise))((function(n,o){function s(t){try{i(r.next(t))}catch(t){o(t)}}function c(t){try{i(r.throw(t))}catch(t){o(t)}}function i(t){var e;t.done?n(t.value):(e=t.value,e instanceof a?e:new a((function(t){t(e)}))).then(s,c)}i((r=r.apply(t,e||[])).next())}))};const n={handle(){return r(this,void 0,void 0,(function*(){const t=paypal_brasil_settings.currency,e=paypal_brasil_settings.client_id,a=paypal_brasil_settings.locale,r=document.getElementById("paypal-sdk-script"),n=`https://www.paypal.com/sdk/js?client-id=${e}&commit=false&currency=${t}&locale=${a}&disable-funding=card`;if(r&&r.getAttribute("src")!==n||!r){const t=document.createElement("script");return t.id="paypal-sdk-script",t.async=!0,t.src=n,t.setAttribute("data-page-type","checkout"),document.head.appendChild(t),new Promise(((e,a)=>{t.onload=()=>{e()},t.onerror=t=>{console.error("Erro ao carregar o script do PayPal:",t),e()}}))}return Promise.resolve()}))}}},"./src/frontend/frontend-shared.ts":(t,e,a)=>{a.r(e),a.d(e,{PaypalPayments:()=>r});class r{static scrollTop(){jQuery("html, body").animate({scrollTop:0},300)}static setNotices(t){jQuery(".woocommerce-notices-wrapper:first").html(t)}static makeRequest(t,e){var a,n=null!==(a=paypal_brasil_bcdc_settings.paypal_brasil_handler_url)&&void 0!==a?a:paypal_brasil_settings.paypal_brasil_handler_url;const o={async:!0,crossDomain:!0,url:r.replaceVars(n,{ACTION:t}),method:"POST",dataType:"json",contentType:"application/json; charset=utf-8",data:JSON.stringify(e)};return jQuery.ajax(o)}static showDefaultButton(){jQuery("#paypal-brasil-button-container .paypal-submit-button-bcdc").hide(),jQuery("#paypal-brasil-button-container .paypal-submit-button").hide(),jQuery("#paypal-brasil-button-container .default-submit-button").show()}static showPaypalButton(){jQuery("#paypal-brasil-button-container .default-submit-button").hide(),jQuery("#paypal-brasil-button-container .paypal-submit-button-bcdc").hide(),jQuery("#paypal-brasil-button-container .paypal-submit-button").show()}static showPaypalBCDCButton(){jQuery("#paypal-brasil-button-container .default-submit-button").hide(),jQuery("#paypal-brasil-button-container .paypal-submit-button").hide(),jQuery("#paypal-brasil-button-container .paypal-submit-button-bcdc").show()}static isPaypalPaymentsSelected(){return!!jQuery("#payment_method_paypal-brasil-spb-gateway:checked").length}static isPaypalBCDCPaymentsSelected(){return!!jQuery("#payment_method_paypal-brasil-bcdc-gateway:checked").length}static triggerUpdateCheckout(){jQuery(document.body).trigger("update_checkout")}static triggerUpdateCart(){jQuery(document.body).trigger("wc_update_cart")}static submitForm(){jQuery("form.woocommerce-checkout, form#order_review").submit()}static submitFormCheckout(){jQuery("form.checkout.woocommerce-checkout").submit()}static replaceVars(t,e){let a=t;for(let t in e)e.hasOwnProperty(t)&&(a=a.replace(new RegExp("{"+t+"}","g"),e[t]));return a}}},"./src/frontend/frontend-shortcut/frontend-shortcut-api.ts":(t,e,a)=>{a.r(e),a.d(e,{paymentShortcut:()=>n});var r=a("./src/frontend/frontend-shared.ts");const n={miniCart:{create:()=>new Promise(((t,e)=>{r.PaypalPayments.makeRequest("shortcut",{nonce:paypal_brasil_settings.nonce}).done((function(e){t(e.data.ec)})).fail((function(t){e(t.responseJSON)}))})),approve:t=>{window.location=r.PaypalPayments.replaceVars(paypal_brasil_settings.checkout_review_page_url,{PAY_ID:t.paymentID,PAYER_ID:t.payerID})},error:t=>{t&&(r.PaypalPayments.setNotices(t.data.error_notice),r.PaypalPayments.scrollTop())},cancel:()=>{r.PaypalPayments.setNotices(paypal_brasil_shortcut_settings.cancel_message),r.PaypalPayments.scrollTop()}},cart:{create:()=>new Promise(((t,e)=>{r.PaypalPayments.makeRequest("shortcut-cart",{nonce:paypal_brasil_settings.nonce}).done((function(e){t(e.data.ec)})).fail((function(t){e(t.responseJSON)}))})),approve:t=>{window.location=r.PaypalPayments.replaceVars(paypal_brasil_settings.checkout_review_page_url,{PAY_ID:t.paymentID,PAYER_ID:t.payerID})},error:t=>{t&&(r.PaypalPayments.setNotices(t.data.error_notice),r.PaypalPayments.scrollTop())},cancel:()=>{r.PaypalPayments.triggerUpdateCart(),r.PaypalPayments.setNotices(paypal_brasil_shortcut_settings.cancel_message),r.PaypalPayments.scrollTop()}}}}},e={};function a(r){var n=e[r];if(void 0!==n)return n.exports;var o=e[r]={exports:{}};return t[r](o,o.exports,a),o.exports}a.d=(t,e)=>{for(var r in e)a.o(e,r)&&!a.o(t,r)&&Object.defineProperty(t,r,{enumerable:!0,get:e[r]})},a.o=(t,e)=>Object.prototype.hasOwnProperty.call(t,e),a.r=t=>{"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(t,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(t,"__esModule",{value:!0})};var r={};(()=>{a.r({});var t=a("./src/frontend/frontend-shared.ts"),e=a("./src/frontend/frontend-shortcut/frontend-shortcut-api.ts"),r=a("./src/frontend/frontend-sdk/frontend-sdk-spb.ts"),n=function(t,e,a,r){return new(a||(a=Promise))((function(n,o){function s(t){try{i(r.next(t))}catch(t){o(t)}}function c(t){try{i(r.throw(t))}catch(t){o(t)}}function i(t){var e;t.done?n(t.value):(e=t.value,e instanceof a?e:new a((function(t){t(e)}))).then(s,c)}i((r=r.apply(t,e||[])).next())}))};class o extends t.PaypalPayments{constructor(){super(),this.initializeShortcut()}initializeShortcut(){return n(this,void 0,void 0,(function*(){yield r.importSpbSdk.handle(),jQuery("body").on("updated_shipping_method",this.renderCartButton).on("updated_wc_div",this.renderCartButton).on("updated_mini_cart",this.renderMiniCartButton),this.renderCartButton(),this.renderMiniCartButton()}))}renderMiniCartButton(){return n(this,void 0,void 0,(function*(){jQuery(".shortcut-button-mini-cart").each(((t,a)=>{paypal.Buttons({locale:"pt_BR",style:{size:"responsive",color:paypal_brasil_settings.style.color,shape:paypal_brasil_settings.style.format,label:"buynow"},createOrder:e.paymentShortcut.miniCart.create,onApprove:e.paymentShortcut.miniCart.approve,onError:e.paymentShortcut.miniCart.error,onCancel:e.paymentShortcut.miniCart.cancel}).render(a)}))}))}renderCartButton(){return n(this,void 0,void 0,(function*(){jQuery(".wc-proceed-to-checkout .shortcut-button").each(((t,a)=>{paypal.Buttons({locale:"pt_BR",style:{size:"responsive",color:paypal_brasil_settings.style.color,shape:paypal_brasil_settings.style.format,label:"buynow"},createOrder:e.paymentShortcut.cart.create,onApprove:e.paymentShortcut.cart.approve,onError:e.paymentShortcut.cart.error,onCancel:e.paymentShortcut.cart.cancel}).render(a)}))}))}}new o})(),a.r(r)})();
//# sourceMappingURL=frontend-shortcut.js.map