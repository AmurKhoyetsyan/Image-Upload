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
                let response;
                try {
                    response = JSON.parse(xhr.responseText);
                } catch (e) {
                    console.error("JSON Parse Error:", e);
                    console.error("Response text:", xhr.responseText);
                    alert('Error: Server returned invalid JSON. Check console for details.');
                    return;
                }
                console.log("Res ::: ", response);

                // Display Gemini response - show nutrition_data as formatted JSON
                const geminiResponseDiv = document.getElementById('gemini-response');
                const nutritionDataDiv = document.getElementById('nutrition-data');

                if (response.nutrition_data) {
                    const nutrition = response.nutrition_data;
                    
                    // Check if nothing was found (readable_text: false or no data)
                    if (nutrition.readable_text === false || 
                        (nutrition.confidence_score === 0 && nutrition.energy_kcal === null && nutrition.fat === null)) {
                        // Show friendly message instead of JSON
                        nutritionDataDiv.innerHTML = '<div style="background: #fff3cd; padding: 20px; border-radius: 5px; border: 1px solid #ffc107; text-align: center;"><p style="color: #856404; margin: 0; font-size: 16px; font-weight: 500;">Nothing found. Please try another image.</p></div>';
                        geminiResponseDiv.style.display = 'block';
                    } else {
                        // Format JSON nicely with proper indentation
                        const jsonString = JSON.stringify(response.nutrition_data, null, 2);
                        
                        let html = '<div style="background: white; padding: 20px; border-radius: 5px; border: 1px solid #ddd;">';
                        html += '<pre style="margin: 0; font-family: "Courier New", Courier, monospace; font-size: 14px; line-height: 1.6; color: #333; white-space: pre-wrap; word-wrap: break-word; overflow-x: auto;">' + 
                                jsonString.replace(/</g, '&lt;').replace(/>/g, '&gt;') + 
                                '</pre>';
                        html += '</div>';
                        
                        nutritionDataDiv.innerHTML = html;
                        geminiResponseDiv.style.display = 'block';
                    }
                } else if (response.gemini && response.gemini.success) {
                    // No nutrition data available
                    nutritionDataDiv.innerHTML = '<div style="background: #fff3cd; padding: 20px; border-radius: 5px; border: 1px solid #ffc107; text-align: center;"><p style="color: #856404; margin: 0; font-size: 16px; font-weight: 500;">Nothing found. Please try another image.</p></div>';
                    geminiResponseDiv.style.display = 'block';
                } else if (response.gemini && !response.gemini.success) {
                    // Show friendly message instead of Gemini error
                    nutritionDataDiv.innerHTML = '<div style="background: #fff3cd; padding: 20px; border-radius: 5px; border: 1px solid #ffc107; text-align: center;"><p style="color: #856404; margin: 0; font-size: 16px; font-weight: 500;">Image not found. Please try another image.</p></div>';
                    geminiResponseDiv.style.display = 'block';
                } else {
                    geminiResponseDiv.style.display = 'none';
                }

            } else {
                let response;
                try {
                    response = JSON.parse(xhr.responseText);
                    console.log("Error ::: ", response);
                    alert('Error uploading image: ' + (response.message || 'Unknown error'));
                } catch (e) {
                    console.error("Error parsing response:", e);
                    console.error("Response text:", xhr.responseText.substring(0, 500));
                    alert('Server error (HTTP ' + xhr.status + '). Server returned HTML instead of JSON. Check server logs.');
                }
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
