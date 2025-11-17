#!/bin/bash

# Quick Laravel Dusk Test Runner using docker-compose run
# This script uses the dusk service defined in docker-compose.yml

set -e

echo "🚀 Running Laravel Dusk Tests in Docker..."
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Step 1: Ensure required services are running
echo -e "${YELLOW}Starting required services...${NC}"
docker-compose up -d app db nginx selenium
sleep 3
echo -e "${GREEN}✓ Services started${NC}"
echo ""

# Step 2: Run migrations and seed
echo -e "${YELLOW}Setting up database...${NC}"
docker-compose exec -T app php artisan migrate:fresh --force
docker-compose exec -T app php artisan db:seed --class=RoleSeeder --force
echo -e "${GREEN}✓ Database ready${NC}"
echo ""

# Step 3: Run Dusk tests using the dusk service
echo -e "${YELLOW}Running Dusk tests...${NC}"
echo ""

if [ $# -eq 0 ]; then
    # Run all tests
    docker-compose --profile testing run --rm dusk php artisan dusk
else
    # Run with provided arguments
    docker-compose --profile testing run --rm dusk php artisan dusk "$@"
fi

TEST_EXIT_CODE=$?

echo ""
if [ $TEST_EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✅ All tests passed!${NC}"
else
    echo -e "${RED}❌ Some tests failed${NC}"
    echo -e "${YELLOW}💡 Tips:${NC}"
    echo -e "  - Check screenshots: tests/Browser/screenshots/"
    echo -e "  - Check console logs: tests/Browser/console/"
    echo -e "  - Watch tests live: http://localhost:7900 (password: secret)"
fi

echo ""
echo "🏁 Test run completed!"

exit $TEST_EXIT_CODE
