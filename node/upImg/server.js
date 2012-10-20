var http = require("http");
var url = require("url");

//route : on passe une function en param
function start(route, handle) {
    function onRequest(request, response) {
        var postData = "";
        var pathname = url.parse(request.url).pathname;
        console.log("Requête reçue pour le chemin " + pathname + ".");

        /*
        request.setEncoding("utf8");

        request.addListener("data", function(postDataChunk) {
            postData += postDataChunk;
            console.log("Paquet POST reçu '"+ postDataChunk + "'.");
        });

        request.addListener("end", function() {
            route(handle, pathname, response, postData, request);
        }); */

       route(handle, pathname, response,postData, request);

//        response.writeHead(200, {"Content-Type": "text/plain"});
//        response.write("Hello World");
//        response.end();
    }
    http.createServer(onRequest).listen(8888);
    console.log("Démarrage du serveur.");
}

exports.start = start;

