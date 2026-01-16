<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Laravel') }}</title>
        <link rel="stylesheet" type="text/css" href="{{ asset('/css/style.css') }}">
    </head>
    <body>
    <div class="parent">
        <div class="parent-dragable">
            <label for="addFile" class="input-label" data-content="Choose a File" multiple="false">
                <p class="drag-title">Select Image Or</p>
                Just drop your file here
            </label>
            <input type="file" accept="image/*" id="addFile" class="input-file" multiple="false" />
        </div>
    </div>
    <script type="text/javascript" src="{{ asset('/js/draganddropchunkupload.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('/js/script.js') }}"></script>
    </body>
</html>
