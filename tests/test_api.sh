#!/bin/bash

echo "1. OBTENER TODOS LOS PARKINGS"
curl -s http://localhost/pk/api/parkings
echo -e "\n\n"

echo "2. CREAR NUEVO PARKING"
curl -s -X POST http://localhost/pk/api/parkings \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "Parking Test",
    "direccion": "Calle Test 123",
    "plazas": 100
  }'
echo -e "\n\n"

echo "3. OBTENER PARKING ESPECÍFICO"
curl -s http://localhost/pk/api/parkings/1
echo -e "\n\n"

echo "4. ACTUALIZAR PARKING"
curl -s -X POST http://localhost/pk/api/parkings/1 \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "Parking Actualizado",
    "plazas": 150
  }'
echo -e "\n\n"

echo "5. PROBAR VALIDACIÓN (ERROR ESPERADO)"
curl -s -X POST http://localhost/pk/api/parkings \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": ""
  }'
echo -e "\n\n"

echo "6. BORRAR PARKING"
curl -s -X DELETE http://localhost/pk/api/parkings/1
echo -e "\n\n"