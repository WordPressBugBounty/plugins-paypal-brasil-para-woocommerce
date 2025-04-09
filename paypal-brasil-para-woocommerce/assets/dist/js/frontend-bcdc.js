/*! For license information please see frontend-bcdc.js.LICENSE.txt */
(()=>{"use strict";var e={"./src/frontend/Utils.ts":(e,t,a)=>{a.r(t),a.d(t,{Utils:()=>n});const n={getInputDataForm:()=>{jQuery("form.checkout").on("change","input, select",(()=>{const e=n.getInputDataFormFromFields();jQuery("#wc-bcdc-brasil-data").val(JSON.stringify(e))}));let e=jQuery("#wc-bcdc-brasil-data"),t={};try{t=JSON.parse(e.val())}catch(e){console.warn("Erro ao parsear JSON do input #wc-bcdc-brasil-data",e)}const a={first_name:"#billing_first_name",last_name:"#billing_last_name",person_type:"#billing_person_type",cpf:"#billing_cpf",cnpj:"#billing_cnpj",email:"#billing_email",postcode:"#billing_postcode",address:"#billing_address",number:"#billing_number",address_2:"#billing_address_2",neighborhood:"#billing_neighborhood",city:"#billing_city",state:"#billing_state",country:"#billing_country",phone:"#billing_phone"};for(const e in a)n.getFormFieldOrFallback(t,e,a[e]);return Object.keys(t).forEach((e=>{"string"==typeof t[e]&&(t[e]=t[e].trim())})),t},getFormFieldOrFallback:(e,t,a)=>{if(!e[t]||""===e[t].toString().trim()){const n=jQuery(a).val();n&&(e[t]=n)}},getInputDataFormFromFields:()=>{const e={},t={first_name:"#billing_first_name",last_name:"#billing_last_name",person_type:"#billing_person_type",cpf:"#billing_cpf",cnpj:"#billing_cnpj",email:"#billing_email",postcode:"#billing_postcode",address:"#billing_address",number:"#billing_number",address_2:"#billing_address_2",neighborhood:"#billing_neighborhood",city:"#billing_city",state:"#billing_state",country:"#billing_country",phone:"#billing_phone"};for(const a in t){const n=jQuery(t[a]).val();e[a]=n?String(n).trim():""}return e}}},"./src/frontend/frontend-bcdc/frontend-bcdc-api.ts":(e,t,a)=>{a.r(t),a.d(t,{paymentBCDC:()=>o});var n=a("./src/frontend/frontend-shared.ts"),r=function(e,t,a,n){return new(a||(a=Promise))((function(r,o){function c(e){try{i(n.next(e))}catch(e){o(e)}}function l(e){try{i(n.throw(e))}catch(e){o(e)}}function i(e){var t;e.done?r(e.value):(t=e.value,t instanceof a?t:new a((function(e){e(t)}))).then(c,l)}i((n=n.apply(e,t||[])).next())}))};const o={create:e=>r(void 0,void 0,void 0,(function*(){var t=paypal_brasil_bcdc_settings.paypal_brasil_handler_url;const a={async:!0,crossDomain:!0,url:n.PaypalPayments.replaceVars(t,{ACTION:"checkout_bcdc"}),method:"POST",dataType:"json",contentType:"application/json; charset=utf-8",data:JSON.stringify(e)};let r;return yield jQuery.ajax(a).done((function(e){console.log("Sucesso:",e.data.payment_id),jQuery("#wc-bcdc-brasil-data").val(JSON.stringify(e.data)),r=e.data.payment_id})).fail((function(e,t,a){console.error("Erro:",t,a,e.responseText);var r=JSON.parse(e.responseText).data.errors;if(console.log(r),r&&"object"==typeof r){let e="";Object.entries(r).forEach((([t,a])=>{e+=`<ul class="woocommerce-error" role="alert"><li>${a}</li></ul>`})),n.PaypalPayments.setNotices(e),n.PaypalPayments.scrollTop()}})),console.log(r),r})),approve:e=>{jQuery("#paypal-bcdc-fields [name=paypal-brasil-bcdc-order-id]").val(e.orderID),jQuery("#paypal-bcdc-fields [name=paypal-brasil-bcdc-payer-id]").val(e.payerID),jQuery("#paypal-bcdc-fields [name=paypal-brasil-bcdc-pay-id]").val(e.paymentID),n.PaypalPayments.submitForm()},error:e=>{const t=jQuery("#wc-bcdc-brasil-api-error-data").val();if(t)n.PaypalPayments.setNotices(JSON.parse(t)),n.PaypalPayments.scrollTop();else{var a='<ul class="woocommerce-error" role="alert"><li>'+e.message+"</li></ul>";n.PaypalPayments.setNotices(a),n.PaypalPayments.scrollTop()}},cancel:()=>{n.PaypalPayments.triggerUpdateCheckout(),n.PaypalPayments.setNotices(paypal_brasil_bcdc_messages.cancel_message),n.PaypalPayments.scrollTop()}}},"./src/frontend/frontend-sdk/frontend-sdk-bcdc.ts":(e,t,a)=>{a.r(t),a.d(t,{importBcdcSdk:()=>r});var n=function(e,t,a,n){return new(a||(a=Promise))((function(r,o){function c(e){try{i(n.next(e))}catch(e){o(e)}}function l(e){try{i(n.throw(e))}catch(e){o(e)}}function i(e){var t;e.done?r(e.value):(t=e.value,t instanceof a?t:new a((function(e){e(t)}))).then(c,l)}i((n=n.apply(e,t||[])).next())}))};const r={handle(){return n(this,void 0,void 0,(function*(){const e=paypal_brasil_bcdc_settings.client_id,t=paypal_brasil_bcdc_settings.currency,a=paypal_brasil_bcdc_settings.locale,n=document.getElementById("paypal-sdk-script"),r=`https://www.paypal.com/sdk/js?client-id=${e}&components=buttons,funding-eligibility,marks,marks&currency=${t}&locale=${a}`;if(n&&n.getAttribute("src")!==r||!n){const e=document.createElement("script");return e.id="paypal-sdk-script",e.async=!0,e.src=r,e.setAttribute("data-page-type","checkout"),document.head.appendChild(e),new Promise(((t,a)=>{e.onload=()=>{t()},e.onerror=e=>{console.error("Erro ao carregar o script do PayPal:",e),t()}}))}return Promise.resolve()}))}}},"./src/frontend/frontend-shared.ts":(e,t,a)=>{a.r(t),a.d(t,{PaypalPayments:()=>o});const n="undefined"!=typeof paypal_brasil_bcdc_settings?paypal_brasil_bcdc_settings:{},r="undefined"!=typeof paypal_brasil_settings?paypal_brasil_settings:{};class o{static scrollTop(){jQuery("html, body").animate({scrollTop:0},300)}static setNotices(e){jQuery(".woocommerce-notices-wrapper:first").html(e)}static makeRequest(e,t){var a,c,l=null!==(c=null!==(a=null==n?void 0:n.paypal_brasil_handler_url)&&void 0!==a?a:null==r?void 0:r.paypal_brasil_handler_url)&&void 0!==c?c:"";const i={async:!0,crossDomain:!0,url:o.replaceVars(l,{ACTION:e}),method:"POST",dataType:"json",contentType:"application/json; charset=utf-8",data:JSON.stringify(t)};return jQuery.ajax(i).done((function(e){console.log("Sucesso:",e)})).fail((function(e,t,a){console.error("Erro:",t,a,e.responseText)}))}static showDefaultButton(){jQuery("#paypal-brasil-button-container .paypal-submit-button-bcdc").hide(),jQuery("#paypal-brasil-button-container .paypal-submit-button").hide(),jQuery("#place_order").removeAttr("style"),jQuery("#paypal-brasil-button-container .default-submit-button").show()}static showPaypalButton(){jQuery("#paypal-brasil-button-container .default-submit-button").hide(),jQuery("#paypal-brasil-button-container .paypal-submit-button-bcdc").hide(),jQuery("#place_order").attr("style","display:none !important"),jQuery("#paypal-brasil-button-container .paypal-submit-button").show()}static showPaypalBCDCButton(){jQuery("#paypal-brasil-button-container .default-submit-button").hide(),jQuery("#paypal-brasil-button-container .paypal-submit-button").hide(),jQuery("#place_order").attr("style","display:none !important"),jQuery("#paypal-brasil-button-container .paypal-submit-button-bcdc").show()}static isPaypalPaymentsSelected(){return!!jQuery("#payment_method_paypal-brasil-spb-gateway:checked").length}static isPaypalBCDCPaymentsSelected(){return!!jQuery("#payment_method_paypal-brasil-bcdc-gateway:checked").length}static triggerUpdateCheckout(){jQuery(document.body).trigger("update_checkout")}static triggerUpdateCart(){jQuery(document.body).trigger("wc_update_cart")}static submitForm(){jQuery("form.woocommerce-checkout, form#order_review").submit()}static submitFormCheckout(){jQuery("form.checkout.woocommerce-checkout").submit()}static replaceVars(e,t){let a=e;for(let e in t)t.hasOwnProperty(e)&&(a=a.replace(new RegExp("{"+e+"}","g"),t[e]));return a}}}},t={};function a(n){var r=t[n];if(void 0!==r)return r.exports;var o=t[n]={exports:{}};return e[n](o,o.exports,a),o.exports}a.d=(e,t)=>{for(var n in t)a.o(t,n)&&!a.o(e,n)&&Object.defineProperty(e,n,{enumerable:!0,get:t[n]})},a.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t),a.r=e=>{"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})};var n={};(()=>{a.r({});var e=a("./src/frontend/frontend-shared.ts"),t=a("./src/frontend/frontend-bcdc/frontend-bcdc-api.ts"),n=a("./src/frontend/frontend-sdk/frontend-sdk-bcdc.ts"),r=a("./src/frontend/Utils.ts"),o=function(e,t,a,n){return new(a||(a=Promise))((function(r,o){function c(e){try{i(n.next(e))}catch(e){o(e)}}function l(e){try{i(n.throw(e))}catch(e){o(e)}}function i(e){var t;e.done?r(e.value):(t=e.value,t instanceof a?t:new a((function(e){e(t)}))).then(c,l)}i((n=n.apply(e,t||[])).next())}))};class c extends e.PaypalPayments{constructor(){super(),this.sdkLoaded=!1,this.clearCheckoutErrors=()=>{jQuery(".woocommerce-error, .woocommerce-message, .woocommerce-info").fadeOut(300,(function(){jQuery(this).remove()}))},this.updateCheckout=(e=null)=>{e&&e.preventDefault(),this.triggerUpdateCheckout()},this.forceUpdateCheckout=(e=null)=>{e&&e.preventDefault(),jQuery(document.body).trigger("update_checkout")},this.triggerUpdateCheckout=this.debounce((()=>{this.forceUpdateCheckout()}),1e3),paypal_brasil_bcdc_settings.is_order_pay_page?this.initializeOrderPage():this.initializeCheckoutBcdc()}initializeOrderPage(){return o(this,void 0,void 0,(function*(){this.addPaypalBCDCButtonOnContainer(),this.renderPayPalButtonBcdc(),jQuery(document).on("updated_checkout","body",this.addPaypalBCDCButtonOnContainer),jQuery(document).on("updated_checkout","body",this.updateCheckoutButtonBcdc),jQuery("form#order_review").on("change","[name=payment_method],#payment_method_paypal-brasil-bcdc-gateway",this.updateCheckoutButtonBcdc),jQuery("form#order_review").on("change","[name=payment_method],#payment_method_paypal-brasil-bcdc-gateway",this.renderPayPalButtonBcdc),jQuery("form#order_review").on("change","[name=payment_method],#payment_method_paypal-brasil-bcdc-gateway",this.forceUpdateCheckout),this.listenInputChanges(),jQuery(document).on("updated_checkout","body",this.renderPayPalButtonBcdc)}))}initializeCheckoutBcdc(){return o(this,void 0,void 0,(function*(){jQuery(document).on("updated_checkout","body",this.addPaypalBCDCButtonOnContainer),jQuery(document).on("updated_checkout","body",this.updateCheckoutButtonBcdc),jQuery("form.woocommerce-checkout, form#order_review").on("change","[name=payment_method],#payment_method_paypal-brasil-bcdc-gateway",this.updateCheckoutButtonBcdc),jQuery("form.woocommerce-checkout, form#order_review").on("change","[name=payment_method],#payment_method_paypal-brasil-bcdc-gateway",this.renderPayPalButtonBcdc),jQuery("form.woocommerce-checkout, form#order_review").on("change","[name=payment_method],#payment_method_paypal-brasil-bcdc-gateway",this.forceUpdateCheckout),this.listenInputChanges(),jQuery(document).on("updated_checkout","body",this.renderPayPalButtonBcdc),jQuery(document).on("updated_checkout","body",this.clearCheckoutErrors)}))}listenInputChanges(){jQuery(["[name=billing_first_name]","[name=billing_last_name]","[name=billing_cpf]","[name=billing_cnpj]","[name=billing_phone]","[name=billing_address_1]","[name=billing_number]","[name=billing_address_2]","[name=billing_neighborhood]","[name=billing_city]","[name=billing_state]","[name=billing_country]","[name=billing_email]"].join(",")).on("keyup",(()=>{e.PaypalPayments.isPaypalBCDCPaymentsSelected()&&this.updateCheckout()})),jQuery(["[name=billing_persontype]"].join(",")).on("change",(()=>{e.PaypalPayments.isPaypalBCDCPaymentsSelected()&&this.updateCheckout()}))}addPaypalBCDCButtonOnContainer(){if(!document.querySelector("#paypal-submit-button-bcdc")){var e=document.createElement("div"),t=document.createElement("div");e.className="paypal-submit-button-bcdc",t.id="paypal-button-bcdc";var a=document.querySelector("#paypal-brasil-button-container");a&&(a.appendChild(e),e.appendChild(t))}}updateCheckoutButtonBcdc(){if(e.PaypalPayments.isPaypalBCDCPaymentsSelected()){var t=jQuery("#wc-bcdc-brasil-selected");t&&t.val("true"),e.PaypalPayments.showPaypalBCDCButton(),console.debug("bcdc change")}else e.PaypalPayments.isPaypalPaymentsSelected()||((t=jQuery("#wc-bcdc-brasil-selected"))&&t.val("false"),this.clearCheckoutErrors,e.PaypalPayments.showDefaultButton())}renderPayPalButtonBcdc(){return o(this,void 0,void 0,(function*(){var a=document.getElementById("payment_method_paypal-brasil-bcdc-gateway");if(a.checked){jQuery("#wc-bcdc-brasil-selected").val("true"),yield n.importBcdcSdk.handle();var l=document.getElementById("paypal-button-bcdc");if(l&&paypal_brasil_bcdc_settings.allowed_currency)l.innerHTML="",[paypal.FUNDING.CARD].forEach((a=>o(this,void 0,void 0,(function*(){const n={style:{layout:"vertical",color:"black",shape:"pill",label:"paypal",tagline:"false"},fundingSource:a,expandCardForm:!!e.PaypalPayments.isPaypalBCDCPaymentsSelected(),createOrder:()=>{try{var a=r.Utils.getInputDataForm();return t.paymentBCDC.create(a)}catch(t){if(e.PaypalPayments.isPaypalBCDCPaymentsSelected())throw t;this.clearCheckoutErrors()}},onApprove:t.paymentBCDC.approve,onError:t.paymentBCDC.error,onCancel:t.paymentBCDC.cancel},o={style:{layout:"vertical",color:"black",shape:"pill",label:"paypal",tagline:"false"},fundingSource:a,createOrder:()=>{try{var e=r.Utils.getInputDataForm();return t.paymentBCDC.create(e)}catch(e){throw e}},onApprove:t.paymentBCDC.approve,onError:t.paymentBCDC.error,onCancel:t.paymentBCDC.cancel},l=paypal.Buttons(n);if(l.isEligible()){l.render("#paypal-button-bcdc");var i=["autoRender:true"];c.sendPaypalLogger("paypal-brasil-bcdc-gateway","Button rendered with Autorender",i)}else{paypal.Buttons(o).render();i=["autoRender:false"];if(c.sendPaypalLogger("paypal-brasil-bcdc-gateway","Button rendered without Autorender",i),paypal.Buttons(o).isEligible()){paypal.Buttons(o).render("#paypal-button-bcdc");i=["autoRender:false"];c.sendPaypalLogger("paypal-brasil-bcdc-gateway","Button rendered without Autorender",i)}}}))))}else{jQuery("#wc-bcdc-brasil-selected").val("false");a.addEventListener("change",(function(){return o(this,void 0,void 0,(function*(){if(a.checked){jQuery("#wc-bcdc-brasil-selected").val("true"),yield n.importBcdcSdk.handle();var l=document.getElementById("paypal-button-bcdc");if(l&&paypal_brasil_bcdc_settings.allowed_currency)l.innerHTML="",[paypal.FUNDING.CARD].forEach((a=>o(this,void 0,void 0,(function*(){const n={style:{layout:"vertical",color:"black",shape:"pill",label:"paypal",tagline:"false"},fundingSource:a,expandCardForm:!!e.PaypalPayments.isPaypalBCDCPaymentsSelected(),createOrder:()=>{try{var a=r.Utils.getInputDataForm();return t.paymentBCDC.create(a)}catch(t){if(e.PaypalPayments.isPaypalBCDCPaymentsSelected())throw t;jQuery(".woocommerce-error, .woocommerce-message, .woocommerce-info").fadeOut(300,(function(){jQuery(this).remove()}))}},onApprove:t.paymentBCDC.approve,onError:t.paymentBCDC.error,onCancel:t.paymentBCDC.cancel},o={style:{layout:"vertical",color:"black",shape:"pill",label:"paypal",tagline:"false"},fundingSource:a,createOrder:()=>{try{var a=r.Utils.getInputDataForm();return t.paymentBCDC.create(a)}catch(t){if(e.PaypalPayments.isPaypalBCDCPaymentsSelected())throw t}},onApprove:t.paymentBCDC.approve,onError:t.paymentBCDC.error,onCancel:t.paymentBCDC.cancel},l=paypal.Buttons(n);if(l.isEligible()){l.render("#paypal-button-bcdc");var i=["autoRender:true"];c.sendPaypalLogger("paypal-brasil-bcdc-gateway","Button rendered with Autorender",i)}else{paypal.Buttons(o).render();i=["autoRender:false"];if(c.sendPaypalLogger("paypal-brasil-bcdc-gateway","Button rendered without Autorender",i),paypal.Buttons(o).isEligible()){paypal.Buttons(o).render("#paypal-button-bcdc");i=["autoRender:false"];c.sendPaypalLogger("paypal-brasil-bcdc-gateway","Button rendered without Autorender",i)}}}))))}}))}))}}))}static sendPaypalLogger(t,a,n=[],r=[],o="info"){e.PaypalPayments.makeRequest("api_logger_handler",{nonce:paypal_brasil_bcdc_settings.nonce,gateway_id:t,message:a,level:o,tags:n,extra:r})}debounce(e,t,a=!1){let n;return function(){const r=this,o=arguments,c=a&&!n;clearTimeout(n),n=setTimeout((function(){n=null,a||e.apply(r,o)}),t),c&&e.apply(r,o)}}}new c})(),a.r(n)})();
//# sourceMappingURL=frontend-bcdc.js.map