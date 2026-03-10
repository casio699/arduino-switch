import serial
import serial.tools.list_ports
import sys
import time
import json
import os
import signal

def signal_handler(sig, frame):
    print('\nClosing serial port...')
    if 'ser' in globals() and ser.is_open:
        ser.close()
    sys.exit(0)

signal.signal(signal.SIGINT, signal_handler)

def find_arduino():
    ports = serial.tools.list_ports.comports()
    for port in ports:
        description = port.description.lower()
        if ("arduino" in description or
        "ch340" in description):
            return port.device

def read_switches():
    switches_path = os.path.join(os.path.dirname(__file__), '..', 'switch control system', 'src', 'switches.json')
    try:
        with open(switches_path, 'r') as f:
            return json.load(f)
    except FileNotFoundError:
        print(f"Error: Could not find switches.json at {switches_path}")
        return None
    except json.JSONDecodeError:
        print("Error: Invalid JSON in switches.json")
        return None

port=find_arduino()
if port is None:
    print("Error: No Arduino found")
    sys.exit(1)

print("Using port: " + port)
try:
    ser = serial.Serial(port, 9600, timeout=1)
    time.sleep(2)
except serial.SerialException as e:
    print(f"Error opening serial port: {e}")
    print("Please make sure the Arduino is connected and not in use by another application")
    sys.exit(1)

while True:
    switches = read_switches()
    if switches is not None:
        switch1_state = switches.get('switch1', False)
        if switch1_state:
            print("Switch1 is ON - Sending '1' to Arduino")
            ser.write(b'1')
        else:
            print("Switch1 is OFF - Sending '0' to Arduino")
            ser.write(b'0')
    
    time.sleep(1)

