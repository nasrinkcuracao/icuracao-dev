require([
  'jquery'
], function($){
  // $('input[name="curacaocustid"]').hide();
  $('.field-curacaocustid').hide();
  //show it when the checkbox is clicked
  $('input[name="account-check"]').on('click', function () {
      if ($(this).prop('checked')) {
          // $('input[name="curacaocustid"]').fadeIn();
          $('.field-curacaocustid').fadeIn();
      } else {
          // $('input[name="curacaocustid"]').hide();
          $('.field-curacaocustid').hide();
      }
  });
});
