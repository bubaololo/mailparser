// readyData.replaceAll(/\W+/gu, ' ');



// const printTasks = function(jsonData) {
//     b = JSON.stringify(jsonData);

//     document.querySelector('.list').textContent = b;
//     // document.body.insertAdjacentHTML('afterbegin', a);
//     console.log(b);
// };
const display = document.querySelector('.list')
// clearList();




document.addEventListener('DOMContentLoaded', () => {

    const ajaxSend = async(formData) => {
        // clearList();
        display.classList.add('_active');

        const fetchResp = await fetch('app.php', {
            method: 'POST',
            body: formData
        });
        if (!fetchResp.ok) {
            throw new Error(`Ошибка по адресу ${url}, статус ошибки ${fetchResp.status}`);
        }
        return await fetchResp.text();
    };

    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            ajaxSend(formData)
                .then((response) => {

                    // form.reset(); // очищаем поля формы
                    // clearList();
                    display.classList.remove('_active');
                    printList();


                })
                .catch((err) => console.error(err))
        });
    });

});

function clearList() {
    display.textContent = '';
}



audioObj = new Audio('notice.mp3');

async function printList() {
    let getData = await fetch('t.json')
    let readyJson = await getData.json()


    if (readyJson == "") {
        alert('на страницах по вашему запросу не нашлось ни одного e-mail')
    } else {
        Object.entries(readyJson).forEach(element => {
            const tableE = document.querySelector('.email');
            const tableU = document.querySelector('.url');
            console.log(tableE);
            console.log(tableU);
            const[email,url] = element;
            const rowE = document.createElement("tr");
            const cellE = document.createElement("td");
            cellE.innerText = email;
            rowE.appendChild(cellE);
            tableE.appendChild(rowE)
            const rowU = document.createElement("tr");
            const cellU = document.createElement("td");
            cellU.innerText = url;
            rowU.appendChild(cellU);
            tableU.appendChild(rowU)
        });
        audioObj.play();
    }
}

// printData();

// sendButton = document.getElementById('send_button');

// sendButton.addEventListener(onclick, printTasks);


// array1.forEach(element => console.log(element));