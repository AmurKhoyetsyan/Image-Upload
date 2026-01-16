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

    DAD.draggedUpload({
        element: document.querySelectorAll(".input-label"),
        input: document.querySelector(".input-file"),
        start: () => {
            startUpload();
        },
        end: (res, err) => {
            if(err === null){
                console.log("Res ::: ", res);
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
            if(err === null){
                console.log("Res ::: ", res);
            }else {
                endUpload();
                console.log("Error ::: ", err);
            }
        }
    });
})();
