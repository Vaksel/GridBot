init();

// function copyToClipboard(elem) {
//     // create hidden text element, if it doesn't already exist
//     var targetId = "_hiddenCopyText_";
//     var isInput = elem.tagName === "INPUT" || elem.tagName === "TEXTAREA";
//     var origSelectionStart, origSelectionEnd;
//     if (isInput) {
//         // can just use the original source element for the selection and copy
//         target = elem;
//         origSelectionStart = elem.selectionStart;
//         origSelectionEnd = elem.selectionEnd;
//     } else {
//         // must use a temporary form element for the selection and copy
//         target = document.getElementById(targetId);
//         if (!target) {
//             var target = document.createElement("textarea");
//             target.style.position = "absolute";
//             target.style.left = "-9999px";
//             target.style.top = "0";
//             target.id = targetId;
//             document.body.appendChild(target);
//         }
//         target.textContent = elem.textContent;
//     }
//     // select the content
//     var currentFocus = document.activeElement;
//     target.focus();
//     target.setSelectionRange(0, target.value.length);
//
//     // copy the selection
//     var succeed;
//     try {
//         succeed = document.execCommand("copy");
//     } catch(e) {
//         succeed = false;
//     }
//     // restore original focus
//     if (currentFocus && typeof currentFocus.focus === "function") {
//         currentFocus.focus();
//     }
//
//     if (isInput) {
//         // restore prior selection
//         elem.setSelectionRange(origSelectionStart, origSelectionEnd);
//     } else {
//         // clear temporary content
//         target.textContent = "";
//     }
//     return succeed;
// }

function init()
{
    let copyItems = document.getElementsByClassName('copyItem');

    for (let i = 0; i < copyItems.length; i++)
    {
        copyItems[i].addEventListener('click', function (event) {
            copyToClipboard(event.target.closest('#paper').parentNode.querySelector('.hidden'));
            document.execCommand("copy");
        });
    }
}

function copyToClipboard(elem) {
    const str = elem.value;
    const el = document.createElement('textarea');
    el.value = str;
    el.setAttribute('readonly', '');
    el.style.position = 'absolute';
    el.style.left = '-9999px';
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
}