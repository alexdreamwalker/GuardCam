# GuardCam
Arduino-based device, web service and web remote controls for home security

## Functionality

The projects consists of three parts:
* Arduino device with servos and motion sensor
* Web PHP server with custom WebSocket implementation
* Cross-browser adaptive web client with camera controls, take screenshot button and alarm

A single Linux pc (for example, Raspberry PI) connected to arduino device via serial port, web camera via USB and running developed php WebSocket full-duplex server. The arduino device contains two servos, connected to web camera to control it's vertical and horizontal position and a motion sensor. When the signal from serial port occures, device is changing the position of the camera according to the command. It also sends a signal via serial port when a motion sensor detecta motion.
Cross-browser WebSocket web client displays camera video stream, has controls for camera position, the button to make a screenshot and audio alarm if the motion detected. 

## Requirements 

* Linux server with apache, php and mjpeg-streamer
* WebSocket-compatible browser
* Web camera with linux drivers available
