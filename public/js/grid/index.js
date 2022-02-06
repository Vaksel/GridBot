const {slideDown, slideUp, slideToggle} = window.domSlider;

var exchangeId = null;

var investmentWarning = null;

init();

function validate(evt, regex) {
    var theEvent = evt || window.event;
    var key = theEvent.keyCode || theEvent.which;
    key = String.fromCharCode( key );
    if( !regex.test(key) ) {
        theEvent.returnValue = false;
        if(theEvent.preventDefault) theEvent.preventDefault();
    }
}

function init()
{
    validationInit();

    eventListenerInit();

    toolTipInit();

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

    setInterval(setCoinCurrentPrice, 5000);
}

function toolTipInit()
{
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
}

function eventListenerInit()
{
    let isToggled = false;

    document.getElementById('toggleExchange').addEventListener('click', function(event){
        if(!isToggled)
        {
            isToggled = true;
            let exchangeSelector = document.getElementById('exchange_slider');

            if(exchangeSelector.classList.contains('hidden'))
            {
                exchangeSelector.classList.remove('hidden');
            }
            else
            {
                exchangeSelector.classList.add('hidden');
            }

            slideToggle({
                element: exchangeSelector,
                slideSpeed: 1500,
                // easing: 'easeInOut'
            }).then(function () {
                isToggled = false;
            });

            document.getElementById("toggleExchange")
                .classList.toggle('arrow-down');
        }

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

        let investment = $('input[name="investments"]').val();

        let exchangeType = $('input[name="exchange_type"]').val();

        if(exchangeType === 1 || exchangeType === 3)
        {
            if(parseFloat(investment) < minInvestment)
            {
                alert('Вы не можете инвестировать так мало!');
            }
            else
            {
                let formData = new FormData(this);
                $('#loader').removeClass('d-none');
                $('#loader').addClass('d-flex');
                $('#createGrid').prop('disabled', true)


                gridCreate(formData);
                // document.querySelector('form').submit();
            }
        }
        else
        {
            let formData = new FormData(this);
            $('#loader').removeClass('d-none');
            $('#loader').addClass('d-flex');
            $('#createGrid').prop('disabled', true)


            gridCreate(formData);
            // document.querySelector('form').submit();
        }

    });

    document.querySelector('.investment-sum__inner div[data-bs-toggle=tooltip]').addEventListener('mouseover', toggleTooltip);

    document.querySelector('.investment-levels').addEventListener('click', showInvestmentLevels);
}

function validationInit()
{
    document.querySelector('.price-interval--high').onkeypress = function() {
        validate(event, /[0-9.]/);
    }

    document.querySelector('.price-interval--high').onpaste = function() {
        validate(event, /[0-9.]/);
    }

    document.querySelector('.price-interval--low').onkeypress = function() {
        validate(event, /[0-9.]/);
    }

    document.querySelector('.price-interval--low').onpaste = function() {
        validate(event, /[0-9.]/);
    }

    document.querySelector('input[name=investments]').onkeypress = function() {
        validate(event, /[0-9.]/);
    }

    document.querySelector('input[name=investments]').onpaste = function() {
        validate(event, /[0-9.]/);
    }

    document.querySelector('input[name=start_price]').onkeypress = function() {
        validate(event, /[0-9.]/);
    }

    document.querySelector('input[name=start_price]').onpaste = function() {
        validate(event, /[0-9.]/);
    }

    document.querySelector('input[name=order_qty]').onkeypress = function() {
        validate(event, /[0-9]/);
    }

    document.querySelector('input[name=order_qty]').onpaste = function() {
        validate(event, /[0-9]/);
    }
}

function toggleTooltip()
{
    let investmentLevelsContainerDom = document.querySelector('.investment-sum__inner div[data-bs-toggle=tooltip]');

    if(!isEmpty(document.querySelector('input[name="investments"]').value))
    {
        investmentLevelsContainerDom.classList.remove('investment-level-locked');
        investmentLevelsContainerDom.setAttribute('data-bs-original-title', 'Уровни инвестирования');
    }
    else
    {
        investmentLevelsContainerDom.classList.add('investment-level-locked');
        investmentLevelsContainerDom.setAttribute('data-bs-original-title', 'Для просмотра уровней инвестирования введите инвестицию');
    }
}

function getInvestmentLevels(investment)
{
    let levels = [];

    let minInvestmentLevel = {
        lvl: 0,
        lvlMinSum: parseFloat(minInvestment).toFixed(minPriceInDecimalPlaces),
        lvlMidAmount: parseFloat(tickerMinQty),
        isCurrent: false,
    };

    levels.push(minInvestmentLevel);

    let currentInvestmentLvl = Math.floor(investment / minInvestment) - 1;

    let currentInvestmentLevel = {
        lvl: currentInvestmentLvl,
        lvlMinSum: parseFloat((minInvestment * currentInvestmentLvl).toFixed(minPriceInDecimalPlaces)),
        lvlMidAmount: tickerMinQty * currentInvestmentLvl
    }

    let i = 0;

    if(currentInvestmentLvl > minInvestmentLevel.lvl)
    {
        let bufferArr = [];

        while(currentInvestmentLvl > minInvestmentLevel.lvl && i < 3)
        {

            let investmentLvl = {
                lvl: currentInvestmentLvl,
                lvlMinSum: parseFloat((minInvestment * (currentInvestmentLvl + 1)).toFixed(minPriceInDecimalPlaces)),
                lvlMidAmount: tickerMinQty * (currentInvestmentLvl + 1),
                isCurrent: currentInvestmentLvl === currentInvestmentLevel.lvl,
            };

            bufferArr.push(investmentLvl);
            currentInvestmentLvl--;

            i++;
        }

        bufferArr = bufferArr.reverse();

        levels = levels.concat(bufferArr);
    }
    else
    {
        levels[0].isCurrent = true;
    }

    currentInvestmentLvl = currentInvestmentLevel.lvl + 1;
    investmentWarning = null;

    let bufferArr = [];

    while(i < 9)
    {
        let investmentLvl = {
            lvl: currentInvestmentLvl,
            lvlMinSum: parseFloat((minInvestment * (currentInvestmentLvl + 1)).toFixed(minPriceInDecimalPlaces)),
            lvlMidAmount: tickerMinQty * (currentInvestmentLvl + 1),
            isCurrent: currentInvestmentLvl === currentInvestmentLevel.lvl,
        };

        currentInvestmentLvl++;
        i++;

        if(investmentLvl.lvl !== minInvestmentLevel.lvl)
        {
            bufferArr.push(investmentLvl);
        }
        else
        {
            investmentWarning = true;
        }
    }

    levels = levels.concat(bufferArr);

    // levels.forEach(lvl => {
    //     lvl.lvlMinSum = lvl.lvlMinSum.toString()
    // });

    return levels;
}

function renderInvestmentLevels(lvls)
{
    let investmentLvlContainerDom = document.querySelector('div.investment-container');
        investmentLvlContainerDom.innerHTML = '';

    let investmentWarningDom = document.querySelector('.investment-warning');

    if(investmentWarningDom)
    {
        investmentWarningDom.remove();
    }

        if(investmentWarning)
        {
            let investmentWarning = document.createElement('h3');
            investmentWarning.className = 'investment-warning text-danger';
            investmentWarning.innerHTML = 'Ваша инвестиция меньше минимальной!';

            document.querySelector('#priceLevelModal .modal-body').insertAdjacentElement('afterbegin', investmentWarning);
        }


    lvls.forEach(lvl => {
            let investmentRow = document.createElement('div');
                investmentRow.className = 'd-flex justify-content-around';

                if(lvl.isCurrent)
                {
                    investmentRow.classList.add('isCurrentLvl');
                }

            let investmentLvlDom = document.createElement('div');
                investmentLvlDom.innerHTML = lvl.lvl;
                investmentLvlDom.className = 'lvl-item';
        investmentRow.insertAdjacentElement('beforeend', investmentLvlDom);

            let minInvestmentSumDom = document.createElement('div');
                minInvestmentSumDom.innerHTML = lvl.lvlMinSum;
                minInvestmentSumDom.className = 'lvl-item';
        investmentRow.insertAdjacentElement('beforeend', minInvestmentSumDom);

            let midInvestmentAmountDom = document.createElement('div');
                midInvestmentAmountDom.innerHTML = lvl.lvlMidAmount;
                midInvestmentAmountDom.className = 'lvl-item';
        investmentRow.insertAdjacentElement('beforeend', midInvestmentAmountDom);

        investmentLvlContainerDom.insertAdjacentElement('beforeend', investmentRow);
    })
}

function calculateInvestmentLevel()
{
    minInvestmentSubFunction();

    let investment = document.querySelector('input[name=investments]').value;

    let loader = document.getElementById('loader').cloneNode(true);
        loader.className = 'level-loader';

    console.log(loader);

    document.querySelector('#priceLevelModal .investment-container').innerHTML = '<br><br>';

    document.querySelector('#priceLevelModal .investment-container').insertAdjacentElement('beforeend',loader);
    setTimeout(function () {
        let levels = getInvestmentLevels(investment);

        renderInvestmentLevels(levels);

        console.log(levels);
    }, 2000);

}

function showInvestmentLevels()
{
    if(!isEmpty(document.querySelector('input[name=investments]').value))
    {
        $('#priceLevelModal').modal('toggle');

        calculateInvestmentLevel();
    }
    else
    {
        return false;
    }
}

function setCoinCurrentPrice()
{
    if($('.tickers')[0].selectize !== undefined)
    {
        let ticker = $('.tickers')[0].selectize.getValue();

        let coinInfoBody = JSON.stringify({
            ticker: ticker,
            exchange_id: exchangeId,
            _token: $('input[name=_token]').val()
        });

        query = `/current-price`;

        let kucoinApiRequestStartPrice = new XMLHttpRequest();
        kucoinApiRequestStartPrice.open('POST', query, true);
        kucoinApiRequestStartPrice.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
        kucoinApiRequestStartPrice.onload = function ()
        {
            let res = kucoinApiRequestStartPrice.response;

            res = JSON.parse(res);

            if(res.status === 'success')
            {
                if(!isEmpty(res.price))
                {
                    res.price = parseFloat(res.price);

                    $('#current-price').html(res.price);
                }
            }
            else
            {
                $('#current-price').html('(выберите токен)');
                console.log('Ошибка при попытке получить цену тикера');
            }

        }

        kucoinApiRequestStartPrice.send(coinInfoBody);
    }
}

function set40PercentsLimitsBinance(ticker)
{
    let rootUrl = 'https://api.binance.com';
    let decimalPlaces = 5, tickerMinQty;

    let query = `/api/v3/exchangeInfo?symbol=${ticker}`;
    let url = rootUrl + query;

    let binanceApiRequestMinPrice = new XMLHttpRequest();

    binanceApiRequestMinPrice.open('GET', url, true);

    binanceApiRequestMinPrice.onload = function ()
    {
        let res = binanceApiRequestMinPrice.response;

        res = JSON.parse(res);

        console.log(res);


        if(!isEmpty(res.symbols[0].filters))
        {
            res.symbols[0].filters.forEach(filterObj =>
            {
                if(filterObj.filterType === 'LOT_SIZE')
                {
                    tickerMinQty = filterObj.minQty;
                }
                if(filterObj.filterType === 'PRICE_FILTER')
                {
                    let minPrice = filterObj.minPrice;
                    decimalPlaces = (minPrice.substr(minPrice.indexOf('.') + 1, minPrice.indexOf('1') - 1)).length;
                }
            });
        }

        query = `/api/v3/ticker/price?symbol=${ticker}`;

        url = rootUrl + query;

        let binanceApiRequestStartPrice = new XMLHttpRequest();

        binanceApiRequestStartPrice.open('GET', url, true);
        binanceApiRequestStartPrice.onload = function ()
        {
            let res = binanceApiRequestStartPrice.response;

            res = JSON.parse(res);

            console.log(res);


            if(!isEmpty(res.price))
            {
                res.price = parseFloat(res.price);
                document.querySelector('input[name="lowest_price"]').value = (res.price - res.price * 0.4).toFixed(decimalPlaces);

                document.querySelector('input[name="highest_price"]').value = (res.price + res.price * 0.4).toFixed(decimalPlaces);
            }
        }

        binanceApiRequestStartPrice.send();
    }

    binanceApiRequestMinPrice.send();
}

function set40PercentsLimitKucoin(ticker)
{
    let rootUrl = 'https://api.kucoin.com';
    let decimalPlaces = 5, tickerMinQty;
    let exchangeTypeId = 2;

    console.log(ticker);

    let coinInfoUrl = `/coin-info`;

    console.log($('input[name=_token]').val());

    let minPriceBody = JSON.stringify({
        ticker: ticker,
        exchange_type: exchangeTypeId,
        _token: $('input[name=_token]').val()
    });

    let coinInfoBody = JSON.stringify({
        ticker: ticker,
        exchange_id: exchangeId,
        _token: $('input[name=_token]').val()
    });

    let kucoinApiRequestMinPrice = new XMLHttpRequest();

    kucoinApiRequestMinPrice.open('POST', coinInfoUrl, true);
    kucoinApiRequestMinPrice.setRequestHeader("Content-Type", "application/json;charset=UTF-8");

    kucoinApiRequestMinPrice.onload = function ()
    {

        let res = kucoinApiRequestMinPrice.response;

        console.log(res);
        res = JSON.parse(res);

        console.log(res);

        if(!isEmpty(res.priceIncrement))
        {
            tickerMinQty = res.baseMinSize;
            let minPrice = res.priceIncrement;
            console.log(minPrice);
            decimalPlaces = (minPrice.substr(minPrice.indexOf('.') + 1, minPrice.indexOf('1') - 1)).length;

        }

        query = `/current-price`;

        let kucoinApiRequestStartPrice = new XMLHttpRequest();
        kucoinApiRequestStartPrice.open('POST', query, true);
        kucoinApiRequestStartPrice.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
        kucoinApiRequestStartPrice.onload = function ()
        {
            let res = kucoinApiRequestStartPrice.response;

            res = JSON.parse(res);

            console.log((res.price - res.price * 0.4));

            if(res.status === 'success')
            {
                if(!isEmpty(res.price))
                {
                    res.price = parseFloat(res.price);
                    console.log(res.price);
                    document.querySelector('input[name="lowest_price"]').value = (res.price - res.price * 0.4).toFixed(decimalPlaces);

                    document.querySelector('input[name="highest_price"]').value = (res.price + res.price * 0.4).toFixed(decimalPlaces);
                }
            }
            else
            {
                alert('Ошибка при попытке получить цену тикера');
            }

        }

        kucoinApiRequestStartPrice.send(coinInfoBody);
    }

    kucoinApiRequestMinPrice.send(minPriceBody);

}

function set40PercentsLimits(ticker)
{
    //
    let exchangeType = $('input[name="exchange_type"]').val();

    if(exchangeType == 1 || exchangeType == 3)
    {
        set40PercentsLimitsBinance(ticker);
    }

    if(exchangeType == 2 || exchangeType == 4)
    {
        set40PercentsLimitKucoin(ticker);
    }
}


function set40PercentsLimitsHandler(event)
{
    if(!isEmpty(event.target.value))
    {
        setCoinCurrentPrice(event.target.value);
        set40PercentsLimits(event.target.value);
    }
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

    selectize.on('change', set40PercentsLimitsHandler);

    set40PercentsLimits($('.tickers')[0].selectize.getValue());


}

async function gridCreate(formData)
{
    createGridFetch(formData).then(res => {
        console.log(res);
        if(!isEmpty(res.status))
        {
            console.log('Идем дальше');
            let volUsageDom = $('.asset_usage_container');

            if(volUsageDom !== null && volUsageDom !== undefined)
            {
                console.log('VolUsageRemove');

                volUsageDom.remove();
            }

            $('#createGrid').prop('disabled', false)
            $('#loader').addClass('d-none');
            $('#loader').removeClass('d-flex');

            if(res.vol !== undefined && res.vol !== null)
            {
                console.log('volWritten');

                $('#createGrid').prop('disabled', false);
                document.querySelector('input[name=grid_id]').value = res.gridId;

                //Общий контейнер для использования разных валют
                volUsageDom = document.createElement('div');
                volUsageDom.className = 'asset_usage_container';
                volUsageDom.innerHTML = '<div class="quote_usage">' +
                    `<span>К-во используемой quote валюты: ${res.vol.quoteAssetInvestment}</span></div>`;
                volUsageDom.innerHTML += '<div class="base_usage">' +
                    `<span>К-во используемой base валюты: ${res.vol.baseAssetInvestment}</span></div>`;

                let confirmBtn = document.createElement('button');
                    confirmBtn.innerHTML = 'Да';
                    confirmBtn.className = 'btn btn-success';
                    confirmBtn.addEventListener('click', confirmedAssetUsage);

                let cancelBtn = document.createElement('button');
                    cancelBtn.innerHTML = 'Нет';
                    cancelBtn.className = 'btn btn-danger';
                    cancelBtn.addEventListener('click', canceledAssetUsage);

                let modalBody = document.querySelector('.modal-body');

                let buttonContainer = document.createElement('div');
                    buttonContainer.className = 'd-flex justify-content-between btn-container';
                    buttonContainer.style = 'margin: auto; width: 150px;';

                    buttonContainer.insertAdjacentElement('beforeend', confirmBtn);
                    buttonContainer.insertAdjacentElement('beforeend', cancelBtn);

                modalBody.appendChild(volUsageDom);
                modalBody.appendChild(buttonContainer);
            }

            console.log('end');



            if(res.errors === null || res.errors === undefined)
            {
                $('#modalTitle').html(res.title);
                $('#modalText').html(res.msg);

                $('#notificationModal').modal('show');
            }
            else
            {
                let errors = res.errors;

                for(let i in errors)
                {
                    let error = document.createElement('p');
                    error.className = 'text-danger';
                    error.innerHTML = errors[i];

                    document.querySelector(`#${i}-error`).insertAdjacentElement('beforeend', error);
                }
            }



            $('#notificationModal').on('hidden.bs.modal', function (e) {

                let gridIdFromDom = document.querySelector('input[name=grid_id]').value;
                if(!isEmpty(gridIdFromDom))
                {
                    deleteGridFetch(gridIdFromDom);
                }

                let assetUsageContainer = document.querySelector('.asset_usage_container');
                let assetUsageBtns = document.querySelector('.btn-container');

                if(assetUsageContainer !== null && assetUsageContainer !== undefined)
                {
                    assetUsageContainer.remove();
                }

                if(assetUsageBtns !== null && assetUsageBtns !== undefined)
                {
                    assetUsageBtns.remove();
                }

                document.querySelector('input[name=grid_id]').value = '';
                document.querySelector('input[name=assetUsageWarned]').value = 0;

            });
        }
    });
}

function confirmedAssetUsage()
{
    document.querySelector('input[name=assetUsageWarned]').value = 1;
    let formData = new FormData(document.forms[0]);
    document.querySelector('input[name=assetUsageWarned]').value = 0;
    document.querySelector('input[name=grid_id]').value = '';
    document.querySelector('.btn-container').remove();
    $('#loader').removeClass('d-none');
    $('#loader').addClass('d-flex');
    $('#createGrid').prop('disabled', true)


    gridCreate(formData);
}

function isEmpty(target)
{
    if(target !== null && target !== undefined && target !== '')
    {
        return false;
    }
    else
    {
        return true;
    }
}

function canceledAssetUsage()
{
    console.log('cancelAssetUsage');

    document.querySelector('input[name=assetUsageWarned]').value = 0;
    $('#notificationModal').modal('hide');

    let btnContainer = document.querySelector('.btn-container');
    let assetUsageContainer = document.querySelector('.asset_usage_container');
    let gridId = document.querySelector('input[name=grid_id]').value;

    btnContainer.remove();
    assetUsageContainer.remove();

    if(!isEmpty(gridId))
    {
        deleteGridFetch(gridId).then(res => {
            console.log(res);
        });
    }

    document.querySelector('input[name=grid_id]').value = '';
}

async function chooseExchange()
{
    let exchangeSliderDom = document.getElementById('exchange_slider');

    if(!exchangeSliderDom.classList.contains('hidden'))
    {
        exchangeSliderDom.classList.add('hidden');
        exchangeId = this.getAttribute('data-exchange-id');

        let data = {_token: $('input[name=_token]').val(), exchange_id: exchangeId};

        chooseExchangeFetch(data).then((res) => {
            console.log(res);
            if(res.status == 'success')
            {
                fillAvailableTickersOnPlatform(res.tickers);

                document.querySelector('input[name=exchange_type]').value = res.exchange_type;
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
}

async function deleteGridFetch(gridId)
{
    let response = await fetch(`/grid-delete/${gridId}`,{
        method: 'GET',
        mode: 'cors', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        // headers: {
        //     'Content-Type': 'application/json'
        //     // 'Content-Type': 'application/x-www-form-urlencoded',
        // },
        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *client
    });

    return await response.json();
}

async function createGridFetch(data)
{
    let response = await fetch('/grid-create',{
        method: 'POST',
        mode: 'cors', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        // headers: {
        //     'Content-Type': 'application/json'
        //     // 'Content-Type': 'application/x-www-form-urlencoded',
        // },
        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *client
        body: data
    });

    return await response.json();
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