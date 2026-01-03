async function sendData(data,action,el = null)
{
  return new Promise((resolve, reject) => {
    $.ajax({
      type: 'POST',
      url: '/ajax/process',
      data:{
        _csrf: yii.getCsrfToken(),
        actionData: data,
        action: action,
        page: 'products',
        access: 1,
      },
      dataType: 'json',
      beforeSend: function(){
        switch(action){
          case 'updateLegalAccountList':
            $('body').prepend('<div id="ajax-loading" style="height: '+documentHeight()+'px"></div>');
            break;
        }
      },
      success: function(resp){
        switch(action){
          case 'updateLegalAccountList':
            $(el)
              .closest('.form-group')
              .nextAll('.form-group')
              .first()
              .find('select.legalaccountnumber-select')
              .html(resp)
              .trigger('change'); // если select2/валидация
            break;
        }
      },
      error: function (jqXHR, textStatus, errorThrown) {
        reject(errorThrown);
      },
      complete: function(){
        $('button').prop('disabled',false);
        $('body #ajax-loading').remove();
      }
    });
  })
}

function sendForm(form,action)
{
  $.ajax({
    type: 'POST',
    url: '/ajax/process',
    data:{
      _csrf: yii.getCsrfToken(),
      formData: $(form).serialize(),
      action: action,
      page: 'products',
      access: 1,
    },
    dataType: 'json',
    beforeSend: function(){
      $('button',form).prop('disabled',true);
      $('body').prepend('<div id="ajax-loading" style="height: '+documentHeight()+'px"></div>');
    },
    success: function(data){
      switch(action){
        case 'submitOrderConfig':
          break;
      }
    },
    complete: function(){
      $('button',form).prop('disabled',false);
      $('body #ajax-loading').remove();
    }
  });
}

$(document).ready(function(){
  $('.site-orders-config').on('change','select.organization-select',function(){
    var data = {};
    data.val = $(this).val();
    sendData(data,'updateLegalAccountList',this);
  });
  $('.site-orders-config').on('submit','form[name="order-config"]',function(e){
    e.preventDefault();

    var form = $(this);

    sendForm(form,'submitOrderConfig');
  });
});
