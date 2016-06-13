var ws, url = 'ws://192.168.1.4:1234/websocket/v2/server.php';

window.onbeforeunload = function() {
  ws.send('quit');
};

function startJob() {
  try {
    ws = new WebSocket(url);
    alert('Connecting... (readyState '+ws.readyState+')');
    ws.onopen = function(msg) {
      alert('Connection successfully opened (readyState ' + this.readyState+')');
    };
    ws.onmessage = function(msg) {
      alert('Server says: '+msg.data);
      if(msg.data == 'motion'){
        playSound('alert.mp3');
      } 
    };
    ws.onclose = function(msg) {
      if(this.readyState == 2)
        alert('Closing... The connection is going throught the closing handshake (readyState '+this.readyState+')');
      else if(this.readyState == 3)
        alert('Connection closed... The connection has been closed or could not be opened (readyState '+this.readyState+')');
      else
        alert('Connection closed... (unhandled readyState '+this.readyState+')');
    };
    ws.onerror = function(event) {
      alert('error :'+event.data);
    };
  }
  catch(exception) {
    alert(exception);
  }
};

function playSound(filename)
{
  var soundDiv = document.getElementById("sound");
  soundDiv.innerHTML = "<object height='0' width='0' data='alert.mp3'></object>";
};

function WebSocketCommand(direction)
{
  ws.send(direction);
}; 
