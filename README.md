# AdaptiveImages
It is based and inspired by [Matt Wilcox Adaptive-Images](https://github.com/MattWilcox/Adaptive-Images). 

Solution to automatically create adapted picture on desired size or more appropriate for devices client.

## Context
- Send smaller weight picture and width the best ratio to the browser.
- Use the same picture on different size on various pages of the website.

## How does it work

### Picture with specific width and height
- Crop and resize on specified size

### Picture with specific width or height
- resize and keep orginal ratio to fit to the required size

### Picture with no specific size require
- resize the picture on maximum size supported by the device


## Required
- imagick for php
- apache mod-rewrite

## Getting Started
Insert following code into .htaccess file

```
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteCond %{REQUEST_URI} !ai-cache
	RewriteRule \.(?:jpe?g|JPE?G|gif|png)$ AdaptiveImages.php
</IfModule>
```

Add this line of javascriptin the <head> of your site

```
<script>document.cookie='resolution='+Math.max(screen.width,screen.height)+'; path=/';</script>
```

## Configuration
You can change the follow options:

- `cachePath` folder storage for cache picture (default `ai-cache`)
- `jpgQuality` compression rate for jpeg file (default `75`)
- `browserCache` Set how long the browser should cache images (default `7 days`)
- `pattern` all of partten for picture
- `resolutions` breakpoints resize

## How to

Add pattern name before picture url
```
<img src="/pattern-name/url/of/the/picture.png" alt="my picture width AdaptiveImamges">
```



## Next step
- library GD php support
- Progressive jpeg
- Customizable picture's filtre
- full retina support for picture


