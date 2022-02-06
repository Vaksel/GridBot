init();

function init()
{
    var selectize = $('.exchanges').selectize({
        sortField: "text",
    });

    initCopyBtns();


    document.getElementById('createExchange').addEventListener('click', createExchange)
}

function copyToClipboardByElem(elem) {
    // create hidden text element, if it doesn't already exist
    var targetId = "_hiddenCopyText_";
    var isInput = elem.tagName === "INPUT" || elem.tagName === "TEXTAREA";
    var origSelectionStart, origSelectionEnd;
    if (isInput) {
        // can just use the original source element for the selection and copy
        target = elem;
        origSelectionStart = elem.selectionStart;
        origSelectionEnd = elem.selectionEnd;
    } else {
        // must use a temporary form element for the selection and copy
        target = document.getElementById(targetId);
        if (!target) {
            var target = document.createElement("textarea");
            target.style.position = "absolute";
            target.style.left = "-9999px";
            target.style.top = "0";
            target.id = targetId;
            document.body.appendChild(target);
        }
        target.textContent = elem.textContent;
    }
    // select the content
    var currentFocus = document.activeElement;
    target.focus();
    target.setSelectionRange(0, target.value.length);

    // copy the selection
    var succeed;
    try {
        succeed = document.execCommand("copy");
    } catch(e) {
        succeed = false;
    }
    // restore original focus
    if (currentFocus && typeof currentFocus.focus === "function") {
        currentFocus.focus();
    }

    if (isInput) {
        // restore prior selection
        elem.setSelectionRange(origSelectionStart, origSelectionEnd);
    } else {
        // clear temporary content
        target.textContent = "";
    }
    return succeed;
}

function initCopyBtns()
{
    let copyItems = document.getElementsByClassName('copyItem');

    console.log(copyItems);

    for (let i = 0; i < copyItems.length; i++)
    {
        copyItems[i].addEventListener('click', function (event) {
            console.log(event);
            copyToClipboardByElem(event.target.closest('.hidden'))
        });
    }
}

function createExchange()
{
    var formData = new FormData(document.forms[0]);

    console.log(formData);

    createExchangeFetch(formData).then((res) => {
        console.log(res);
        if(res.status == 'success')
        {
            console.log(res.status);
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