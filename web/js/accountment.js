function sendFileForm(formData,form,action)
{
  $.ajax({
     type: 'POST',
     url: '/ajax/process',
     data: formData,
     processData: false, // обязательно!
     contentType: false, // обязательно!
     dataType: 'json',
     beforeSend: function() {
       $('button', form).prop('disabled', true);
       $('body').prepend('<div id="ajax-loading" style="height: ' + documentHeight() + 'px"></div>');
     },
     success: function(data) {
       switch (action) {
         case 'postBankAccounts':
          $(data['message']).insertAfter( $(form) );
           break;
       }
     },
     complete: function() {
       $('button', form).prop('disabled', false);
       $('#ajax-loading').remove();
     }
   });
}


$(document).ready(function(){
  $('.site-accountment section#postAccounts form').submit(function(e){
    e.preventDefault();
    var section = $(this).closest('section');
    var form = this;
    var formObj = $(this);
    $('.alert',section).remove();

    var formData = new FormData(form);
    formData.append('_csrf', yii.getCsrfToken());
    formData.append('action', 'postBankAccounts');
    formData.append('page', 'accountment');
    formData.append('access', 1);

    sendFileForm(formData,formObj,'postBankAccounts');
  })
});
