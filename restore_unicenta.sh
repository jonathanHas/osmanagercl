#!/bin/bash

# Restore unicenta database with foreign key checks disabled
echo "Restoring unicenta2016 database..."
echo "This will disable foreign key checks temporarily to avoid cross-database constraint issues."

mysql -u unicenta_user -pstrongpassword -P 3307 unicenta2016 << EOF
SET FOREIGN_KEY_CHECKS=0;
SOURCE dump_unicenta_2025-09-11.sql;
SET FOREIGN_KEY_CHECKS=1;
EOF

echo "Restore complete!"