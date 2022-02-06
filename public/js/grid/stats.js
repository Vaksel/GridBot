// function diffSubtract(date1, date2) {
//     return date2 - date1;
// }

let res = new Date();
let gridCreating = new Date(gridCreatingTime * 1000);

let clock = document.getElementById('clock');

moment.locale('ru');

clock.innerText = moment([gridCreating.getFullYear(), gridCreating.getMonth(), gridCreating.getDate(),
    gridCreating.getHours(), gridCreating.getMinutes(), gridCreating.getSeconds()]).fromNow();

console.log(gridCreatingTime);

console.log(gridCreating.getDate());

console.log();