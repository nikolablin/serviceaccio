async function sendData(data,action)
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
          case 'removeCpcProject':
          case 'removeMarketingMonthData':
            $('body').prepend('<div id="ajax-loading" style="height: '+documentHeight()+'px"></div>');
            break;
          case 'getIncomeBrandYearData':
            break;
        }
      },
      success: function(data){
        switch(action){
          case 'removeCpcProject':
            if(data['deleted']){
              $('.site-reports section#marketingReport .cpc-projects-list .project[data-project-id="'+data['projectId']+'"]').fadeOut(200, function(){ $(this).remove(); });
            }
            else {
              alert(data['message']);
            }
            break;
          case 'getMarketingReportAllPeriodsData':
            resolve(data);
            break;
          case 'removeMarketingMonthData':
            $('#marketingReport .data-form').html(data['message']);
            break;
          case 'getIncomeBrandYearData':
            $('#incomeBrandsReport form .form-group').hide();
            $('#incomeBrandsReport form input[name="date-year"]').attr('disabled',true);
            $('#incomeBrandsReport form .months-data').html(data);
          	$('.digits-field').mask("Z", {translation: {'Z': {pattern: /[0-9,.,-]/, recursive: true }}});
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
        case 'createSalesReport':
        case 'createBuyesReport':
        case 'createMovesReport':
        case 'createComissionerReport':
        case 'createMarketingReport':
          $('section .alert.alert-download-file').remove();
          $(data).insertAfter(form);
          break;
        case 'createIncomeBrandsReport':
          $('section .alert.alert-download-file').remove();
          $('#incomeBrandsReport form .months-data').html(data);
          break;
        case 'getMarketingReportData':
          $('#marketingReport .data-form').html(data);
        	$('.digits-field').mask("Z", {translation: {'Z': {pattern: /[0-9,.,-]/, recursive: true }}});
          break;
        case 'getMarketingReportCpcData':
          $('#marketingReport .cpc-data-form').html(data);
        	$('.digits-field').mask("Z", {translation: {'Z': {pattern: /[0-9,.,-]/, recursive: true }}});
          break;
        case 'setMarketingReportData':
          $('#marketingReport .data-form').html(data);
          break;
        case 'setMarketingReportCpcData':
          $('#marketingReport .cpc-data-form').html(data);
          break;
        case 'addMarketingCPCProjects':
          $('input,select',form).val('');
          $(data).appendTo( $('#marketingReport .cpc-projects-list') );
          break;
      }
    },
    complete: function(){
      $('button',form).prop('disabled',false);
      $('body #ajax-loading').remove();
    }
  });
}

function ajaxSubmitFormWithFile($form, action)
{
  var fd = new FormData($form[0]);

  // Добавим CSRF (актуально для Yii2)
  var csrfParam = $('meta[name="csrf-param"]').attr('content');
  var csrfToken = $('meta[name="csrf-token"]').attr('content');
  if (csrfParam && csrfToken) fd.append(csrfParam, csrfToken);
  fd.append('action',action);

  $.ajax({
    url: '/ajax/process',
    type: 'POST',
    data: fd,
    processData: false,
    contentType: false,
    dataType: 'json',
    xhr: function () {
      var xhr = $.ajaxSettings.xhr();
      if (xhr.upload) {
        xhr.upload.addEventListener('progress', function (e) {
          if (e.lengthComputable) {
            var percent = Math.round(e.loaded * 100 / e.total);
            console.log('Загрузка: ' + percent + '%');
            $form.trigger('uploadProgress', percent);
          }
        }, false);
      }
      return xhr;
    },
    beforeSend: function () {
      $('button',$form).prop('disabled',true);
      $('body').prepend('<div id="ajax-loading" style="height: '+documentHeight()+'px"></div>');
    },
    complete: function () {
      $('button',$form).prop('disabled',false);
      $('body #ajax-loading').remove();
    },
    success: function (res) {
      console.log(res);
      $('section .alert.alert-download-file').remove();
      $(res).insertAfter($form);

      // if (res && res.success) {
      //   if (typeof onSuccess === 'function') onSuccess(res);
      //   else alert(res.message || 'Файл успешно загружен');
      // } else {
      //   if (typeof onError === 'function') onError(res);
      //   else alert(res.error || 'Ошибка обработки файла');
      // }
    },
    error: function (xhr) {
      if (typeof onError === 'function') onError(xhr);
      else alert('Ошибка запроса: ' + (xhr.responseText || xhr.status));
    }
  });
}

window.setYearMonthMarketingFillings = async function()
{
  var data = {};
  try {
    var allPeriods = await sendData(data, 'getMarketingReportAllPeriodsData');
    return allPeriods;
  } catch (error) {
    console.error('Ошибка при получении данных:', error);
    return false;
  }
}

$(document).ready(function(){
  setYearMonthMarketingFillings();

  $(document).on('submit','.site-reports section#salesReport form',function(e){
  // $('.site-reports section#salesReport form').submit(function(e){
    e.preventDefault();
    var section = $(this).closest('section');
    var form = $(this);
    $('.alert',section).remove();
    sendForm(form,'createSalesReport');
  })
  $('.site-reports section#movesReport form').submit(function(e){
    e.preventDefault();
    var section = $(this).closest('section');
    var form = $(this);
    $('.alert',section).remove();
    sendForm(form,'createMovesReport');
  })
  $('.site-reports section#buyerReport form').submit(function(e){
    e.preventDefault();
    var section = $(this).closest('section');
    var form = $(this);
    $('.alert',section).remove();
    sendForm(form,'createBuyesReport');
  })
  $('.site-reports section#comissionerReport form').submit(function(e){
    e.preventDefault();
    var section = $(this).closest('section');
    var form = $(this);
    $('.alert',section).remove();
    sendForm(form,'createComissionerReport');
  })
  $('.site-reports section#incomeBrandsReport form button.next').click(function(){
    var form = $(this).closest('form');
    if(!$('input[name="date-year"]',form).val()){
      alert('Выберите год');
      return false;
    }
    var data = {};
    data.year = $('input[name="date-year"]',form).val();
    sendData(data,'getIncomeBrandYearData');
  });
  $('.site-reports section#incomeBrandsReport form').on('click','button.reset',function(){
    var form = $(this).closest('form');
    $('input[name="date-year"]',form).attr('disabled',false).val('');
    $('.months-data',form).empty();
    $('.form-group',form).show();
  });
  $('.site-reports section#incomeBrandsReport form').submit(function(e){
    e.preventDefault();
    var section = $(this).closest('section');
    var form = $(this);
    $('input[name="date-year"]',form).prop('disabled',false);
    $('.alert',section).remove();
    $('.form-group',form).show();
    sendForm(form,'createIncomeBrandsReport');
  })
  $('.site-reports section#marketingReport form[name="marketing-report-data"]').submit(function(e){
    e.preventDefault();
    var form = $(this);
    sendForm(form,'getMarketingReportData');
  })
  $('.site-reports section#marketingReport form[name="marketing-report-cpc-projects"]').submit(function(e){
    e.preventDefault();
    var form = $(this);
    sendForm(form,'addMarketingCPCProjects');
  })
  $('.site-reports section#marketingReport').on('submit','form#marketing-data-issue-form',function(e){
    e.preventDefault();
    var form = $(this);
    sendForm(form,'setMarketingReportData');
  });
  $('.site-reports section#marketingReport').on('click','form#marketing-data-issue-form button.cancel-editting',function(){
    $('#marketingReport .data-form').html('<h3>Заполненные данные месяца и года</h3>Выберите месяц и год');
    $('#marketingReport input[name="add-data-date"]').val('');
  });
  $('.site-reports section#marketingReport').on('click','form#marketing-data-issue-form h2 button.remove-month-data',function(){
    if(confirm('Вы уверены, что хотите удалить все данные этого месяца?')){
      var data = {};
      data.date = $('section#marketingReport form[name="marketing-report-data"] input[name="add-data-date"]').val();

      sendData(data,'removeMarketingMonthData');
    }
  });
  $('.site-reports section#marketingReport').on('click','form#marketing-cpc-data-issue-form button.cancel-editting',function(){
    $('#marketingReport .cpc-data-form').html('<h3>Заполненные данные месяца и года</h3>Выберите месяц и год');
    $('#marketingReport input[name="cpc-add-data-date"],#marketingReport select[name="cpc-project"]').val('');
  });
  $('.site-reports section#marketingReport').on('submit','form#marketing-report',function(e){
    e.preventDefault();

    var form = $(this);

    sendForm(form,'createMarketingReport');
  });
  $('.site-reports section#marketingReport form[name="marketing-report-cpc-data"]').submit(function(e){
    e.preventDefault();
    var form = $(this);
    sendForm(form,'getMarketingReportCpcData');
  })
  $('.site-reports section#marketingReport').on('submit','form#marketing-cpc-data-issue-form',function(e){
    e.preventDefault();
    var form = $(this);
    sendForm(form,'setMarketingReportCpcData');
  });
  $('.site-reports section#marketingReport').on('click','.cpc-projects-list .project .remove-cpc-project',function(){
    if (confirm('Вы уверены, что хотите удалить проект?')) {
      var project = $(this).closest('.project');
      var data = {};
      data.projectId = $(project).attr('data-project-id');
      sendData(data,'removeCpcProject');
    }
  })
  $('.site-reports section#realizeReport').on('submit', 'form[name="realize-report"]', function(e) {
      e.preventDefault();
      var $form = $(this);
      ajaxSubmitFormWithFile($form,'createRealizeReport');
  });
});
