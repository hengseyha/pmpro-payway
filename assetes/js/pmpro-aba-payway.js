// Used by plugns that hide/show the billing fields.
pmpro_require_billing = false;

jQuery(document).ready(function ($) {
  $("#pmpro_message").remove();
  // add default payment
  let html =
    '<input type="hidden" name="aba_paway_option" id="aba_payment-otpion" value="cards"/>';
  jQuery("#pmpro_form").append(html);

  $('input[name="selecteLevel"]').on("click", function () {
    $('input[name="selecteLevel"]').prop("checked", false);
    $(this).prop("checked", true);
    let getValue = $(this).val();
    $("#pmpro_level").val(getValue);
  });

  //choosing payment method
  jQuery("input[name=gateway]").click(function () {
    if (jQuery(this).val() == "aba_payway") {
      // jQuery("#pmpro_paypalexpress_checkout").hide();
      jQuery("#pmpro_billing_address_fields").hide();
      jQuery("#pmpro_payment_information_fields").show();
      jQuery("#pmpro_submit_span").show();
    }
  });

  //select the radio button if the label is clicked on
  jQuery("a.pmpro_radio").click(function () {
    jQuery(this).prev().click();
  });

  jQuery(".payment-otpion-lists").on("click", "li", function () {
    let method = jQuery(this).attr("data-option");
    jQuery("#aba_payment-otpion").val(method);
    jQuery(".payment-otpion-lists li").removeClass("active");
    jQuery(this).addClass("active");
  });

  $("#discount_code_button").on("click", function () {
    var code = jQuery("#pmpro_discount_code_aba_payment").val();
    var level_id = jQuery("#pmpro_level").val();
    if (!level_id) {
      // If the level ID is not set, try to get it from the #level field for outdated checkout templates.
      level_id = jQuery("#level").val();
    }

    if (code) {
      //hide any previous message
      jQuery(".pmpro_discount_code_msg").hide();

      //disable the apply button
      jQuery("#pmpro_discount_code_button").attr("disabled", "disabled");
      jQuery("#discount_code_button").attr("disabled", "disabled");

      jQuery.ajax({
        url: pmpro.ajaxurl,
        type: "GET",
        timeout: pmpro.ajax_timeout,
        dataType: "html",
        data:
          "action=applydiscountcode&code=" +
          code +
          "&pmpro_level=" +
          level_id +
          "&msgfield=pmpro_message",
        error: function (xml) {
          alert("Error applying discount code [1]");

          //enable apply button
          jQuery("#pmpro_discount_code_button").removeAttr("disabled");
          jQuery("#discount_code_button").removeAttr("disabled");
        },
        success: function (responseHTML) {
          if (responseHTML == "error") {
            alert("Error applying discount code [2]");
          } else {
            jQuery("#discount_code_message").html(responseHTML);
          }

          //enable invite button
          jQuery("#pmpro_discount_code_button").removeAttr("disabled");
          jQuery("#discount_code_button").removeAttr("disabled");
          jQuery("#discount_code_message").show();
        },
      });
    }
  });
});
