<html>
<head>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"></script>
</head>
<body style="background-image:url('https://webpay3g.transbank.cl/webpayserver/imagenes/background.gif')">
<form name="WS1" id="WS1" action="{$urlAction}" method="POST">
    {$fields}
    <input type="submit" id="submit_webpayplus_payment_form" style="visibility: hidden;">
</form>
<script>
    $(document).ready(function () {
        $("#WS1").submit();
    });
</script>
</body>
</html>