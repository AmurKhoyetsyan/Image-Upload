;(function(){
    function startUpload() {
        let loader = document.querySelector('.parent-upload-loader');

        if (!!loader) {
            if (!loader.classList.contains('active')) {
                loader.classList.add('active');
            }
        }
    }
    function endUpload() {
        let loader = document.querySelector('.parent-upload-loader');

        if (!!loader) {
            if (loader.classList.contains('active')) {
                loader.classList.remove('active');
            }
        }
    }

    function uploadFile(file) {
        const formData = new FormData();
        formData.append('image', file);
        formData.append('_token', document.querySelector('input[name="_token"]').value);

        const xhr = new XMLHttpRequest();

        xhr.open('POST', '/upload', true);

        xhr.onload = function() {
            endUpload();
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                console.log("Res ::: ", response);

                // Display Gemini response
                const geminiResponseDiv = document.getElementById('gemini-response');
                const geminiTextDiv = document.getElementById('gemini-text');

                if (response.gemini && response.gemini.success && response.gemini.text) {
                    geminiTextDiv.textContent = response.gemini.text;
                    geminiResponseDiv.style.display = 'block';
                } else if (response.gemini && !response.gemini.success) {
                    geminiTextDiv.textContent = 'Ошибка Gemini: ' + (response.gemini.message || 'Unknown error');
                    geminiTextDiv.style.color = '#d32f2f';
                    geminiResponseDiv.style.display = 'block';
                } else {
                    geminiResponseDiv.style.display = 'none';
                }

            } else {
                const response = JSON.parse(xhr.responseText);
                console.log("Error ::: ", response);
                alert('Error uploading image: ' + (response.message || 'Unknown error'));
            }
        };

        xhr.onerror = function() {
            endUpload();
            console.log("Error ::: ", 'Network error');
            alert('Network error occurred while uploading image');
        };

        xhr.send(formData);
    }

    DAD.draggedUpload({
        element: document.querySelectorAll(".input-label"),
        input: document.querySelector(".input-file"),
        start: () => {
            startUpload();
        },
        end: (res, err) => {
            if(err === null && res && res.files && res.files.length > 0){
                uploadFile(res.files[0].file);
            }else {
                endUpload();
                console.log("Error ::: ", err);
            }
        }
    });

    DAD.fileChange({
        element: document.querySelectorAll(".input-file"),
        start: () => {
            startUpload();
        },
        end: (res, err) => {
            if(err === null && res && res.files && res.files.length > 0){
                uploadFile(res.files[0].file);
            }else {
                endUpload();
                console.log("Error ::: ", err);
            }
        }
    });
})();
