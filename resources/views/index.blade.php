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
        <form class="parent-dragable" method="POST" action="{{ route('image.upload') }}" enctype="multipart/form-data" id="uploadForm">
            @csrf
            <label for="addFile" class="input-label" data-content="Choose a File" multiple="false">
                <p class="drag-title">Select Image Or</p>
                Just drop your file here
            </label>
            <input type="file" accept="image/*" id="addFile" name="image" class="input-file" multiple="false" />
        </form>
    </div>
    <div class="parent-upload-loader">
        <div data-loader="box-rectangular" loader-color="#46419D" title="Loading..." title-color="#46419D" size="80"></div>
    </div>
    <script type="text/javascript" src="{{ asset('/js/draganddropchunkupload.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('/js/box-rectangular.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('/js/script.js') }}"></script>
    </body>
</html>
