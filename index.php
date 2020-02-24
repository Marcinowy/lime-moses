<html>
<head>
<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.4.1/css/bootstrap.min.css">
<title>Mojżesz xD</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
<meta content="yes" name="apple-mobile-web-app-capable">
</head>
<body class="text-center">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
    <h1>Mojżesz xD</h1>
    <div class="btn btn-success" id="start">Start</div>
    <div id="log"></div>
<script>
function getLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(showPosition);
    } else { 
        alert("Geolocation is not supported by this browser.");
    }
}

var getVehicles = (long,lat) => {
    $.ajax({
        url: "ajax.php",
        type: "POST",
        data: JSON.stringify({type: "callClosest", long: long, lat: lat}),
        contentType: "application/json; charset=utf-8",
        success: (data) => {
            if (data.success)
                $("#log").append("<div>Success: " + data.result.success + " Error: " + data.result.error + " Called ids: " + data.ids.join(", ") + "</div>");
            else
                alert(data.error);
        }
    })
}
function showPosition(position) {
    getVehicles(position.coords.longitude,position.coords.latitude);
}
$(document).ready(function() {
    $("#start").click(function() {
        if ($(this).hasClass("btn-success")) {
            $(this).removeClass("btn-success").addClass("btn-danger").html("Stop");
            getLocation();
            intervalID = setInterval(() => getLocation(), 10000);
        } else {
            $(this).addClass("btn-success").removeClass("btn-danger").html("Start");
            clearInterval(intervalID);
        }
    });
})
</script>
</body></html>