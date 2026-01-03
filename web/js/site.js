window.documentHeight = function()
{
  return $(document).outerHeight();
}

window.ajaxIconCenter = function()
{
  return $(window).scrollTop() + ($(window).height() / 2);
}

$(document).ready(function(){
  $('.period-calendar-field').datepicker({
    lang: 'ru-RU',
    rangeSeparator: ' - ',
    type: 'date-range',
    format: 'dd.MM.yyyy',
    placeholder: 'Выберите период',
  });

  $('.calendar-field').datepicker({
    lang: 'ru-RU',
    rangeSeparator: ' - ',
    type: 'date',
    format: 'dd.MM.yyyy',
    placeholder: 'Выберите дату',
  });

  $('.month-calendar-field').datepicker({
    lang: 'ru-RU',
    rangeSeparator: ' - ',
    type: 'month',
    format: 'dd.MM.yyyy',
    placeholder: 'Выберите дату',
  });
 
  $('.month-marketing-calendar-field').datepicker({
    lang: 'ru-RU',
    rangeSeparator: ' - ',
    type: 'month',
    format: 'dd.MM.yyyy',
    placeholder: 'Выберите дату',
    onShow: async function(e){
      var allPeriods = await setYearMonthMarketingFillings();

      if(allPeriods){
        var calendar = $('body .gmi-picker-panel[data-role="month"]').eq(1);

        Object.entries(allPeriods).forEach(([year, months]) => {
          Object.entries(months).forEach(([month, monthdata]) => {
            var cell = $('.gmi-month-table tr td[data-year="'+year+'"][data-month="'+(month-1)+'"] .cell',calendar);

            switch(monthdata.emptyExist){
              case true:
                $('<span class="status status-notok"></span>').appendTo( $(cell) );
                break;
              case false:
                $('<span class="status status-ok"></span>').appendTo( $(cell) );
                break;
            }

          });
        });
      }

    }
  });

  $('.year-calendar-field').datepicker({
    lang: 'ru-RU',
    rangeSeparator: ' - ',
    type: 'year',
    format: 'dd.MM.yyyy',
    placeholder: 'Выберите год',
  });

	$('.digits-field').mask("Z", {translation: {'Z': {pattern: /[0-9,.,-]/, recursive: true }}});
});
