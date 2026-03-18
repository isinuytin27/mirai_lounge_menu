<!DOCTYPE html>
<html lang="ru">
<head>
    
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Mirai LOCALHOST</title>
    
    <link rel="stylesheet" href="assets/css/reset.css">
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="assets/css/main.css">
    
    <link rel="stylesheet" href="assets/css/slider.css">
    <link rel="stylesheet" href="assets/css/menu.css">
    <link rel="stylesheet" href="assets/css/booking.css">
    
    <script defer src="assets/js/slider.js"></script>
    <script>
      document.addEventListener("DOMContentLoaded", function () {
        // инициализация слайдера
        var slider = new SimpleAdaptiveSlider(".slider", {
          autoplay: true,
          interval: 10000,
        });
      });
    </script>
</head>
<body>
    
    <div id="viewport">
        
        <?php include "screens/about.php"; ?>
        <?php include "screens/booking.php"; ?>
        <?php include "screens/home.php"; ?>
        <?php include "screens/menu.php"; ?>
        <?php include "screens/gallery.php"; ?>
        
    </div>
    
    <script src="assets/js/app.js"></script>
    <script src="assets/js/navigation.js"></script>
    <script src="assets/js/gestures.js"></script>
    <script src="assets/js/gallery.js"></script>
    
</body>
</html>