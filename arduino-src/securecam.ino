#include <Servo.h>
//our servos - vertical and horizontal
Servo verticalServo;
Servo horizontalServo;
//pin names - for motion sensor and servos
int motionPin = 2;
int verticalPin = 6;
int horizontalPin = 9;
//signals - for turning servos
const int upSignal = 2;
const int downSignal = 3;
const int leftSignal = 4;
const int rightSignal = 5;
const int alertSignal = 6;
//current position of servos
int verticalPos = 0;
int horizontalPos = 0;
boolean lock = false;
//signle turn step for servos
int turnStep = 5;

void setup()
{
 verticalServo.attach(verticalPin);
 verticalServo.write(verticalPos);
 horizontalServo.attach(horizontalPin);
 horizontalServo.write(horizontalPos); 
 pinMode(motionPin, INPUT); 
 Serial.begin(9600);
}

void turnRight()
{
  horizontalPos = horizontalPos + turnStep;
  if(horizontalPos > 180) horizontalPos = 180;
  horizontalServo.write(horizontalPos);
}

void turnLeft()
{
  horizontalPos = horizontalPos - turnStep;
  if(horizontalPos < 0) horizontalPos = 0;
  horizontalServo.write(horizontalPos);
}

void turnUp()
{
  verticalPos = verticalPos + turnStep;
  if(verticalPos > 180) verticalPos = 180;
  verticalServo.write(verticalPos);
}

void turnDown()
{
  verticalPos = verticalPos - turnStep;
  if(verticalPos < 0) verticalPos = 0;
  verticalServo.write(verticalPos);
}

void loop()
{
  if(digitalRead(motionPin) == HIGH)
  {
    if(!lock) Serial.write(alertSignal);
    lock = true;
  } else lock = false;
 
  
  if(Serial.available() > 0)
  {
   int inc = Serial.read() - 48;
   Serial.println(inc);
   switch(inc)
   {
     case upSignal: turnUp(); break;
     case downSignal: turnDown(); break;
     case leftSignal: turnLeft(); break;
     case rightSignal: turnRight(); break;
   }
  }
}
