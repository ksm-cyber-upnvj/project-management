#!/bin/bash

# Laravel Dusk Test Runner for Docker
# This script helps run Laravel Dusk tests inside Docker containers

set -e

echo "🚀 Starting Laravel Dusk Tests in Docker..."
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if .env.dusk.docker exists
if [ ! -f .env.dusk.docker ]; then
    echo -e "${RED}Error: .env.dusk.docker not found!${NC}"
    exit 1
fi

# Step 1: Ensure Selenium container is running
echo -e "${YELLOW}Step 1: Starting Selenium Chrome container...${NC}"
docker-compose up -d selenium
echo -e "${GREEN}✓ Selenium container started${NC}"
echo ""

# Step 2: Wait for Selenium to be ready
echo -e "${YELLOW}Step 2: Waiting for Selenium to be ready...${NC}"
for i in {1..30}; do
    if docker-compose exec -T selenium curl -s http://localhost:4444/status > /dev/null 2>&1; then
        echo -e "${GREEN}✓ Selenium is ready!${NC}"
        break
    fi
    if [ $i -eq 30 ]; then
        echo -e "${RED}Error: Selenium failed to start in time${NC}"
        exit 1
    fi
    echo -n "."
    sleep 1
done
echo ""

# Step 3: Ensure app and db containers are running
echo -e "${YELLOW}Step 3: Ensuring app and database containers are running...${NC}"
docker-compose up -d app db nginx
sleep 3
echo -e "${GREEN}✓ Application containers started${NC}"
echo ""

# Step 4: Copy .env.dusk.docker to .env.dusk.local inside container
echo -e "${YELLOW}Step 4: Configuring test environment...${NC}"
docker-compose exec -T app cp .env.dusk.docker .env.dusk.local
echo -e "${GREEN}✓ Environment configured${NC}"
echo ""

# Step 5: Clear config cache
echo -e "${YELLOW}Step 5: Clearing config cache...${NC}"
docker-compose exec -T app php artisan config:clear
echo -e "${GREEN}✓ Config cache cleared${NC}"
echo ""

# Step 6: Run migrations for testing database
echo -e "${YELLOW}Step 6: Running database migrations...${NC}"
docker-compose exec -T app php artisan migrate:fresh --env=dusk.local --force
echo -e "${GREEN}✓ Migrations completed${NC}"
echo ""

# Step 7: Seed roles (if RoleSeeder exists)
echo -e "${YELLOW}Step 7: Seeding database...${NC}"
if docker-compose exec -T app php artisan db:seed --class=RoleSeeder --env=dusk.local --force 2>/dev/null; then
    echo -e "${GREEN}✓ Database seeded${NC}"
else
    echo -e "${YELLOW}⚠ RoleSeeder not found or already seeded (this is okay)${NC}"
fi
echo ""

# Step 8: Run Dusk tests
echo -e "${YELLOW}Step 8: Running Dusk tests...${NC}"
echo ""

# Pass any arguments to the dusk command
if [ $# -eq 0 ]; then
    # Run all tests
    docker-compose exec -T app php artisan dusk
else
    # Run with provided arguments
    docker-compose exec -T app php artisan dusk "$@"
fi

TEST_EXIT_CODE=$?

echo ""
if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
else
    echo -e "${RED}✗ Some tests failed${NC}"
    echo -e "${YELLOW}Check screenshots at: tests/Browser/screenshots/${NC}"
    echo -e "${YELLOW}Check console logs at: tests/Browser/console/${NC}"
    echo -e "${YELLOW}Watch tests live at: http://localhost:7900 (password: secret)${NC}"
fi

echo ""
echo "🏁 Test run completed!"

exit $TEST_EXIT_CODE
