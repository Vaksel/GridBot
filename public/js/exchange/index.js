init();

function init()
{
    var selectize = $('.exchanges').selectize({
        sortField: "text",
    });

    $('#notificationModal').modal({
        backdrop: true,
        keyboard: true,
        show: false,
    });

    document.getElementById('createExchange').addEventListener('click', createExchange)
}


function createExchange(event)
{
    event.preventDefault();
    let selectize = $('.exchanges')[0].selectize;

    if(selectize)
    {
        $('input[name=exchange_type_id]').val(selectize.getValue());
    }

    var formData = {
        name : $('input[name=name]').val(),
        _token: $('input[name=_token]').val(),
        api_key: $('input[name=api_key]').val(),
        api_secret: $('input[name=api_secret]').val(),
        api_passphrase: $('input[name=api_passphrase]').val(),
        exchange_type_id: $('input[name=exchange_type_id]').val()
    };

    createExchangeFetch(formData).then((res) => {

        console.log(res);

        if(res.status == 'success')
        {
            let isConfirm = confirm(res.msg);

            if(isConfirm)
            {
                document.location = 'http://binance.blueberrywebstudio.com/exchanges';
            }
        }
        else
        {
            $('#notificationModal .modal-body').html(res.msg);
            $('#notificationModal').modal('show');
        }
    });
}

async function createExchangeFetch(data)
{
    let response = await fetch('/exchange-create',{
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