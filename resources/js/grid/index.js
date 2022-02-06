const {slideDown, slideUp, slideToggle} = window.domSlider;

init();

function init()
{
    document.getElementById('toggleExchange').addEventListener('click', function(event){
        let exchangeSelector = document.getElementById('exchange_slider');

        slideToggle({
            element: exchangeSelector,
            slideSpeed: 1500,
            // easing: 'easeInOut'
        });

        document.getElementById("toggleExchange")
            .classList.toggle('arrow-down');
    });

    document.querySelector('form').addEventListener('submit', function (event)
    {
        event.preventDefault();

        let selectize = $('.tickers')[0].selectize;

        if(selectize)
        {
            // $('.tickers')[0].selectize.destroy();
            $('input[name=ticker]').val(selectize.getValue());
        }

        document.querySelector('form').submit();
    });

    new Splide('#exchange_slider',{
        type:'fade',
        rewind: true,
        pagination: false
    }).mount();


    let exchangeItems = document.getElementsByClassName('exchange_item');

    for(let i = 0; i < exchangeItems.length; i++)
    {
        exchangeItems[i].addEventListener('click', chooseExchange);
    }
}

async function chooseExchangeFetch(data)
{
    let response = await fetch('/choose-exchange-for-grid',{
        method: 'POST',
        mode: 'cors', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        headers: {
            'Content-Type': 'application/json'
            // 'Content-Type': 'application/x-www-form-urlencoded',
        },
        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *client
        body: JSON.stringify(data)
    });

    return await response.json();
}

function fillAvailableTickersOnPlatform(tickers)
{
    let tickerOptions = '';

    for(let i = 0; i < tickers.length; i++)
    {
        tickerOptions += `<option value="${tickers[i]}">${tickers[i]}</option>`
    }

    document.querySelector('.tickers').innerHTML = tickerOptions;

    if($('.tickers')[0].selectize)
    {
        $('.tickers')[0].selectize.destroy();
    }

    var selectize = $('.tickers').selectize({
        sortField: "text",
    });

}

async function chooseExchange()
{
    let exchangeId = this.getAttribute('data-exchange-id');

    let data = {_token: $('input[name=_token]').val(), exchange_id: exchangeId};

    chooseExchangeFetch(data).then((res) => {
        console.log(res);
        if(res.status == 'success')
        {
            fillAvailableTickersOnPlatform(res.tickers);

            document.querySelector('input[name=exchange_id]').value = res.exchange_id;

            let exchangeSelector = document.getElementById('exchange_slider');
            let form = document.querySelector('.createGridForm');

            document.getElementById("toggleExchange")
                .classList.toggle('arrow-down');

            slideDown({
                element: form,
                slideSpeed: 1500,
                // easing: 'easeInOut'
            });

            slideUp({
                element: exchangeSelector,
                slideSpeed: 1500,
                // easing: 'easeInOut'
            });
        }
    });
}