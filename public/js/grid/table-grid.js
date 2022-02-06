var gridId = null;
var deleteUrl = null;
var archiveUrl = null;

//Накидываем обработчики для кнопок удаления gridView
$(document).ready(function() {
    init();
})

function init()
{
    console.log('init');

    let deleteBtns = document.getElementsByClassName('gridDelete');

    console.log(deleteBtns);

    for (let i = 0; i < deleteBtns.length; i++)
    {
        deleteBtns[i].addEventListener('click', deleteChose);
    }

    $('#removeChoser__modal').modal({
        backdrop: true,
        keyboard: true,
        show: false,
    });

}

function deleteChose()
{
    deleteUrl = this.href;
    gridId = deleteUrl.substring(deleteUrl.indexOf('delete/') + 7, deleteUrl.length);

    archiveUrl = '/grid-archive/' + gridId;
    $('#removeChoser__modal').modal('show');

    console.log('delChose');

    console.log(this);
    console.log(this.href);

    return false;
}

function deleteGrid()
{
    window.open(deleteUrl, '_blank');
}

function archiveGrid()
{
    window.open(archiveUrl, '_blank');
}