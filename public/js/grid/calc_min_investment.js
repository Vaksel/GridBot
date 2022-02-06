var orderQty, halfOrderQty;

var useBaseAsset = false, baseAsset, quoteAsset;

var lowestPrice, highestPrice, startPrice, costPerOrder;

var priceInterval;

var investments;

var ticker = null;

var binanceApiRootUrl = 'https://api.binance.com';

var tickerMinQty = 0, decimalPlaces = 0, tickerMinNotional = 0,
    minInvestment = 0, minPriceInDecimalPlaces = 0;

calculateMinimumInvestment();

function minInvestmentSubFunction()
{
    let exchangeType = getExchangeType();

    console.log(exchangeType);

    if(true)
    {
        let selectize = $('.tickers')[0].selectize;

        console.log(selectize);

        if(selectize)
        {
            // $('.tickers')[0].selectize.destroy();
            ticker = selectize.getValue();
        }

        let ApiRequestStartPriceBody =
            {
                ticker: ticker,
                exchange_id: exchangeId,
                _token: $('input[name=_token]').val(),
            };

        ApiRequestStartPriceBody = JSON.stringify(ApiRequestStartPriceBody);

        if(ticker !== null && $('input[name="lowest_price"]').val() !== '' && $('input[name="highest_price"]').val() !== '' && $('input[name="order_qty"]').val() !== '')
        {
            let binanceApiRequestStartPrice = new XMLHttpRequest();

            if(exchangeType.exchangeIsBinance)
            {
                let query = `/api/v3/ticker/price?symbol=${ticker}`;

                let url = binanceApiRootUrl + query;

                binanceApiRequestStartPrice.open('GET', url, true);
            }
            if(exchangeType.exchangeIsKucoin)
            {
                let query = `/current-price`;

                binanceApiRequestStartPrice.open('POST', query, true);
                binanceApiRequestStartPrice.setRequestHeader("Content-Type", "application/json;charset=UTF-8");

            }

            binanceApiRequestStartPrice.onload = function () {

                let res = binanceApiRequestStartPrice.response;

                res = JSON.parse(res);

                if(res.price !== '' && res.price !== null && res.price !== undefined)
                {
                    startPrice = parseFloat(res.price);

                    readFieldsForMinInvestment();

                    setPriceInterval();

                    setHalfOrderQty();

                    let binanceApiRequestMinPrice = new XMLHttpRequest();

                    if(exchangeType.exchangeIsBinance)
                    {
                        url = binanceApiRootUrl + `/api/v3/exchangeInfo?symbol=${ticker}`;

                        binanceApiRequestMinPrice.open('GET', url, true);
                    }

                    if(exchangeType.exchangeIsKucoin)
                    {
                        var ApiRequestMinPriceBody =
                            {
                                ticker: ticker,
                                exchange_type: 2,
                                _token: $('input[name=_token]').val(),
                            };

                        ApiRequestMinPriceBody = JSON.stringify(ApiRequestMinPriceBody);

                        url = `/coin-info`;

                        binanceApiRequestMinPrice.open('POST', url, true);
                        binanceApiRequestMinPrice.setRequestHeader("Content-Type", "application/json;charset=UTF-8");
                    }

                    binanceApiRequestMinPrice.onload = function ()
                    {
                        let res = binanceApiRequestMinPrice.response;
                        res = JSON.parse(res);

                        if(exchangeType.exchangeIsBinance)
                        {
                            if(res.symbols[0].baseAsset !== null && res.symbols[0].baseAsset !== undefined
                                && res.symbols[0].quoteAsset !== null && res.symbols[0].quoteAsset !== undefined )
                            {
                                baseAsset = res.symbols[0].baseAsset;
                                quoteAsset = res.symbols[0].quoteAsset;

                                toggleCoinForUse();
                            }

                            if(res.symbols[0].filters !== '' && res.symbols[0].filters !== null && res.symbols[0].filters !== undefined)
                            {
                                res.symbols[0].filters.forEach(filterObj =>
                                {
                                    if(filterObj.filterType === 'LOT_SIZE')
                                    {
                                        tickerMinQty = filterObj.minQty;
                                        console.log(tickerMinQty.substr(tickerMinQty.indexOf('.') + 1, tickerMinQty.indexOf('1')));
                                        decimalPlaces = (tickerMinQty.substr(tickerMinQty.indexOf('.') + 1, tickerMinQty.indexOf('1') - 1)).length;
                                    }
                                    if(filterObj.filterType === 'MIN_NOTIONAL')
                                    {
                                        tickerMinNotional = filterObj.minNotional;
                                    }
                                    if(filterObj.filterType === 'PRICE_FILTER')
                                    {
                                        minPriceInDecimalPlaces = filterObj.minPrice.substr(
                                            filterObj.minPrice.indexOf('.') + 1, filterObj.minPrice.indexOf('1')
                                        ).length;
                                    }
                                });

                                fillOrders();

                                $('input[name="investments"]').prop('readonly', false);
                            }
                        }

                        if(exchangeType.exchangeIsKucoin)
                        {
                            if(res.baseCurrency !== null && res.baseCurrency !== undefined
                                && res.quoteCurrency !== null && res.quoteCurrency !== undefined )
                            {
                                baseAsset = res.baseCurrency;
                                quoteAsset = res.quoteCurrency;

                                if(res.baseMinSize !== '' && res.baseMinSize !== null && res.baseMinSize !== undefined)
                                {
                                    tickerMinQty = res.baseMinSize;
                                    decimalPlaces = (tickerMinQty.substr(tickerMinQty.indexOf('.') + 1, tickerMinQty.indexOf('1') - 1)).length;
                                    tickerMinNotional = res.baseMinSize * res.priceIncrement;
                                    minPriceInDecimalPlaces = res.priceIncrement.substr(
                                        res.priceIncrement.indexOf('.') + 1, res.priceIncrement.indexOf('1')
                                    ).length;

                                    console.log(res.priceIncrement);
                                    console.log(minPriceInDecimalPlaces);

                                    fillOrders();

                                    $('input[name="investments"]').prop('readonly', false);
                                }

                                toggleCoinForUse();
                            }
                        }

                        if($('input[name="currency_used"]').val() == '')
                        {
                            $('input[name="currency_used"]').val(quoteAsset);
                        }
                    }

                    if(exchangeType.exchangeIsBinance)
                    {
                        binanceApiRequestMinPrice.send();
                    }
                    if(exchangeType.exchangeIsKucoin)
                    {
                        binanceApiRequestMinPrice.send(ApiRequestMinPriceBody);
                    }

                }
            };
            if(exchangeType.exchangeIsBinance)
            {
                binanceApiRequestStartPrice.send();
            }
            if(exchangeType.exchangeIsKucoin)
            {
                binanceApiRequestStartPrice.send(ApiRequestStartPriceBody);
            }
        }
    }
    else
    {
        $('input[name="investments"]').prop('readonly', false);
    }
}

function getExchangeType()
{
    let exchangeType = $('input[name="exchange_type"]').val();

    let exchangeIsBinance = exchangeType == 1 || exchangeType == 3;
    let exchangeIsKucoin = exchangeType == 2 || exchangeType == 4;

    return {exchangeIsBinance: exchangeIsBinance, exchangeIsKucoin: exchangeIsKucoin};
}

function calculateMinimumInvestment()
{
    $('.parallel-coin h4').click(function()
    {
        let parallelCoinDom = document.querySelector('.parallel-coin h4');

        let parallelCoinDomText = parallelCoinDom.innerText;
        let parallelCoinDomTextFirstPart = parallelCoinDomText.substr(0, parallelCoinDomText.indexOf(' ') + 1);

        if(useBaseAsset)
        {
            $('input[name="currency_used"]').val(quoteAsset);
            $('input[name="alt_used"]').val("false");
            parallelCoinDomText = parallelCoinDomTextFirstPart + baseAsset;
        }
        else
        {
            $('input[name="currency_used"]').val(baseAsset);
            $('input[name="alt_used"]').val("true");
            parallelCoinDomText = parallelCoinDomTextFirstPart + quoteAsset;
        }

        parallelCoinDom.innerText = parallelCoinDomText;

        useBaseAsset = !useBaseAsset;
    });

    document.querySelector('input[name="investments"]').addEventListener('click', minInvestmentSubFunction);
}

function toggleCoinForUse()
{
    let parallelCoinDom = document.querySelector('.parallel-coin h4');

    let parallelCoinDomText = parallelCoinDom.innerText;
    let parallelCoinDomTextFirstPart = parallelCoinDomText.substr(0, parallelCoinDomText.indexOf(' ') + 1);

    console.log(parallelCoinDomTextFirstPart);

    if(useBaseAsset)
    {
        parallelCoinDomText = parallelCoinDomTextFirstPart + quoteAsset;
    }
    else
    {
        parallelCoinDomText = parallelCoinDomTextFirstPart + baseAsset;
    }

    parallelCoinDom.innerText = parallelCoinDomText;

    document.querySelector('.parallel-coin').classList.remove('d-none');
    document.querySelector('.parallel-coin').classList.add('d-flex');


}

function readFieldsForMinInvestment()
{
    lowestPrice = $('input[name="lowest_price"]').val();
    highestPrice = $('input[name="highest_price"]').val();

    orderQty = $('input[name="order_qty"]').val();

    let selectize = $('.tickers')[0].selectize;

    if(selectize)
    {
        ticker = selectize.getValue();
    }
}

function setPriceInterval()
{
    priceInterval = (highestPrice - lowestPrice) / orderQty;
}

function setHalfOrderQty()
{
    halfOrderQty = Math.floor(orderQty / 2);
}

async function setStartPrice()
{
    let res = await getSymbolPrice();

    res.then(res => {
        console.log(res);
    })
    // console.log(res);

    if(res.price != '' && res.price != null && res.price != undefined)
    {
        startPrice = res.price;
    }
}

async function getSymbolPrice()
{
    let query = `/api/v3/ticker/price?symbol=${ticker}`;

    let url = binanceApiRootUrl + query;

    let binanceApiRequest = new XMLHttpRequest();

    binanceApiRequest.open('GET', url, true);
    // binanceApiRequest.onload = function () {
    //     console.log(binanceApiRequest.response)
    // };
    binanceApiRequest.send();

    return binanceApiRequest;

    // let response = await fetch(`https://api.binance.com/api/v3/ticker/price?symbol=${ticker}`,{
    //     method: 'GET',
    //     // mode: 'no-cors', // no-cors, *cors, same-origin
    //     // cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
    //     // credentials: 'same-origin', // include, *same-origin, omit
    //     headers: {
    //         'Content-Type': 'application/json',
    //         'Origin': 'http://binance.blueberrywebstudio.com'
    //         // 'Content-Type': 'application/x-www-form-urlencoded',
    //     },
    //     redirect: 'follow', // manual, *follow, error
    //     referrerPolicy: 'no-referrer', // no-referrer, *client
    // });
    //
    // return await response.json();
}

async function getSymbolMinPrice()
{
    let response = await fetch(`https://api.binance.com/api/v3/exchangeInfo?symbol=${ticker}`,{
        method: 'GET',
        mode: 'cors', // no-cors, *cors, same-origin
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, *same-origin, omit
        headers: {
            'Content-Type': 'application/json'
            // 'Content-Type': 'application/x-www-form-urlencoded',
        },
        redirect: 'follow', // manual, *follow, error
        referrerPolicy: 'no-referrer', // no-referrer, *client
    });

    return await response.json();
}

function calculateMinQtyForOrder(price)
{
    let minQty = tickerMinNotional / price;

    minQty = minQty >= tickerMinQty ? minQty : tickerMinQty;

    return minQty;
}

function fillOrders()
{
    let buyOrders = [
        {
            'price' : parseFloat(lowestPrice),
            'qty' : calculateMinQtyForOrder(lowestPrice)
        }
    ];
    let i = 0;

    minInvestment = 0;

    minInvestment += buyOrders[0].qty * buyOrders[0].price;

    console.log(minInvestment);

    if(i > halfOrderQty)
    {
        highestLimitBuyOrder = startPrice - priceInterval;
    }
    else
    {
        highestLimitBuyOrder = startPrice;
    }

    while(buyOrders[i]['price'] + priceInterval < startPrice)
    {
        let price = buyOrders[i]['price'] + priceInterval;
        let qty = calculateMinQtyForOrder(lowestPrice);
        buyOrders.push({'price' : price, 'qty' : qty});
        i++;
        minInvestment += price * qty;
    }

    console.log(buyOrders);

    if(i > halfOrderQty)
    {
        highestLimitBuyOrder = startPrice - priceInterval;
    }
    else
    {
        highestLimitBuyOrder = startPrice;
    }

    let sellOrders = [
        {
            'price': startPrice + priceInterval,
            'qty' : calculateMinQtyForOrder(lowestPrice)
        }
    ];
    i = 0;

    while(sellOrders[i]['price'] + priceInterval < highestPrice)
    {
        let price = sellOrders[i]['price'] + priceInterval;
        let qty = sellOrders[0].qty;
        sellOrders.push({'price': price, 'qty': qty});
        i++;
        minInvestment += price * qty;
    }

    console.log(sellOrders);


    minInvestment = parseFloat(minInvestment).toFixed(5);

    document.querySelector('[name="investments"]').placeholder = 'Мин инвестиция: ' + minInvestment + ' ' + quoteAsset;


}