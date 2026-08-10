<?php
/**
 * PIX checkout fields template
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

?>

<div >
    <div class="payment-pix-description">
        <img src="<?php echo esc_url( plugins_url( 'assets/images/banner_pix_pp.png', PAYPAL_PAYMENTS_MAIN_FILE ) ); ?>"
             style="max-width: 100%; margin: 0 auto; max-height: 100%; float: none;">
    </div>
</div>

<style> 
.step-one{
     margin-right:10px;
}
 .step-two{
     margin-left:10px;
}
 .payment-pix-description, .payment-pix-step, .payment-pix-flow {
     display: flex;
     flex-direction: column;
     place-items: center flex-start;
}
 .payment-pix-header-image {
     display: flex;
     height: 47px;
     width: 133px;
     margin-top: 24px;
     margin-bottom: 32px;
     background-image: url(<?php echo plugins_url('assets/images/logo-pix.svg', PAYPAL_PAYMENTS_MAIN_FILE) ?>);
     background-repeat: no-repeat;
}
 .payment-pix-header-image-main {
     margin-top: 32px;
     margin-bottom: 48px;
}
 .payment-pix-flow {
     flex-direction: row;
     flex-wrap: nowrap;
     justify-content: space-around;
     place-items: flex-start;
     width: 100%;
     margin-bottom: 32px;
}
 .payment-pix-step {
     position: relative;
     width: 30%;
}
 .payment-pix-step-text, .payment-pix-step-number {
     text-align: center;
}
 .payment-pix-step-number {
     width: 34px;
     padding: 6px 0 6px 0;
     font-size: 16px;
     font-weight: 500;
     color: #4bb8a9;
     border: 1px solid #e3e4e6;
     border-radius: 50%;
}
 .payment-pix-step-arrow {
     position: absolute;
     top: 12px;
     left: 70%;
     background-image: url(<?php echo plugins_url('assets/images/arrow-step.svg', PAYPAL_PAYMENTS_MAIN_FILE) ?>);
     background-repeat: no-repeat;
     background-size: contain;
     background-position: center;
     width: 130%;
     height: 15px;
}
 .payment-pix-step-text {
     margin-top: 16px;
     font-size: 12px;
}
 .payment-pix-step1-text {
     width: 150px;
}
 .payment-pix-step2-text {
     width: 200px;
}
 .box-payment-pix {
     background-color: white;
     border-radius: 4px;
     margin: -7px;
}
 .payment-pix-info {
     margin-top: 8px;
     padding-bottom: 32px;
     font-size: 14px;
     color: #666666;
     text-align: center;
     background-image: url(../img/payment-pix-down-arrow.svg);
     background-repeat: no-repeat;
     background-position: center 36px;
}
 @media (min-width: 768px) {
     .payment-pix-mobile {
         display: none;
    }
}
 @media (max-width: 767px) {
     .payment-pix-pc {
         display: none;
    }
     .box-payment-pix {
         margin-bottom: -25px;
    }
     .payment-pix-header-image {
         margin-bottom: 12px;
    }
     .payment-pix-header-image-main {
         margin-top: 24px;
         margin-bottom: 24px;
    }
     .payment-pix-flow {
         margin-bottom: 32px;
         flex-direction: column;
         align-items: center;
    }
     .payment-pix-step {
         flex-direction: row;
         width: 80%;
         margin-top: 16px;
    }
     .payment-pix-step-arrow {
         right: -35%;
         width: 55%;
    }
    


</style>