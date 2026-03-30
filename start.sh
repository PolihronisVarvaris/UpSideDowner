#!/bin/bash

echo "🚀 Starting PHP server..."

php -S localhost:8080 -t public &

SERVER_PID=$!

sleep 1

echo "🌐 Opening browser..."

xdg-open http://localhost:8080/game.html

echo "🛑 Press CTRL+C to stop server"

wait $SERVER_PID