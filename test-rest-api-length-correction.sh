#!/bin/bash
# Test script for REST API length correction feature
# Run from project root: bash test-rest-api-length-correction.sh

# Configuration
WP_URL="http://localhost:8080"
WP_USER="admin"
WP_PASS="admin"

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== AI Featured Image - REST API Length Correction Test ===${NC}\n"

# Step 1: Create a test post
echo -e "${YELLOW}Step 1: Creating test post...${NC}"
POST_ID=$(docker exec postimagegenerator-wordpress-1 wp post create \
  --post_title="Künstliche Intelligenz im Jahr 2025" \
  --post_status=draft \
  --user=admin \
  --porcelain 2>/dev/null)

if [ -z "$POST_ID" ]; then
  echo -e "${RED}Failed to create post. Is Docker running?${NC}"
  exit 1
fi

echo -e "${GREEN}✓ Created post with ID: $POST_ID${NC}\n"

# Step 2: Test with auto-correction disabled (to see initial result)
echo -e "${YELLOW}Step 2: Testing without auto-correction (baseline)...${NC}"
RESPONSE_NO_CORRECT=$(curl -s -X POST "${WP_URL}/wp-json/ai-featured-image/v1/generate-post" \
  -H "Content-Type: application/json" \
  -u "${WP_USER}:${WP_PASS}" \
  -d "{
    \"post_id\": ${POST_ID},
    \"length\": \"short\",
    \"auto_correct\": false
  }")

# Check if OpenAI API key is configured
if echo "$RESPONSE_NO_CORRECT" | grep -q "api_key_missing"; then
  echo -e "${RED}✗ OpenAI API key not configured!${NC}"
  echo -e "${YELLOW}Please configure the API key in WordPress:${NC}"
  echo -e "  1. Go to ${WP_URL}/wp-admin/options-general.php?page=ai-featured-image"
  echo -e "  2. Enter your OpenAI API key"
  echo -e "  3. Save settings\n"
  
  # Cleanup
  docker exec postimagegenerator-wordpress-1 wp post delete $POST_ID --force 2>/dev/null
  exit 1
fi

# Extract word counts
INITIAL_WORDS=$(echo "$RESPONSE_NO_CORRECT" | grep -o '"initial":[0-9]*' | grep -o '[0-9]*')
FINAL_WORDS_NO_CORRECT=$(echo "$RESPONSE_NO_CORRECT" | grep -o '"final":[0-9]*' | grep -o '[0-9]*')
VALID_NO_CORRECT=$(echo "$RESPONSE_NO_CORRECT" | grep -o '"valid":[a-z]*' | grep -o '[a-z]*$')

echo -e "Initial word count: ${BLUE}${INITIAL_WORDS}${NC} words"
echo -e "Final word count: ${BLUE}${FINAL_WORDS_NO_CORRECT}${NC} words"
echo -e "Valid: ${BLUE}${VALID_NO_CORRECT}${NC}\n"

# Step 3: Test with auto-correction enabled
echo -e "${YELLOW}Step 3: Testing WITH auto-correction...${NC}"
RESPONSE_WITH_CORRECT=$(curl -s -X POST "${WP_URL}/wp-json/ai-featured-image/v1/generate-post" \
  -H "Content-Type: application/json" \
  -u "${WP_USER}:${WP_PASS}" \
  -d "{
    \"post_id\": ${POST_ID},
    \"length\": \"short\",
    \"auto_correct\": true,
    \"max_corrections\": 2
  }")

INITIAL_WORDS_2=$(echo "$RESPONSE_WITH_CORRECT" | grep -o '"initial":[0-9]*' | grep -o '[0-9]*')
FINAL_WORDS_CORRECT=$(echo "$RESPONSE_WITH_CORRECT" | grep -o '"final":[0-9]*' | grep -o '[0-9]*')
VALID_CORRECT=$(echo "$RESPONSE_WITH_CORRECT" | grep -o '"valid":[a-z]*' | grep -o '[a-z]*$')
CORRECTIONS_MADE=$(echo "$RESPONSE_WITH_CORRECT" | grep -o '"made":[0-9]*' | grep -o '[0-9]*' | head -1)

echo -e "Initial word count: ${BLUE}${INITIAL_WORDS_2}${NC} words"
echo -e "Final word count: ${BLUE}${FINAL_WORDS_CORRECT}${NC} words"
echo -e "Corrections made: ${BLUE}${CORRECTIONS_MADE}${NC}"
echo -e "Valid: ${BLUE}${VALID_CORRECT}${NC}\n"

# Step 4: Test all lengths
echo -e "${YELLOW}Step 4: Testing all length options with auto-correction...${NC}\n"

for LENGTH in short medium long verylong; do
  echo -e "${BLUE}Testing length: ${LENGTH}${NC}"
  
  RESPONSE=$(curl -s -X POST "${WP_URL}/wp-json/ai-featured-image/v1/generate-post" \
    -H "Content-Type: application/json" \
    -u "${WP_USER}:${WP_PASS}" \
    -d "{
      \"post_id\": ${POST_ID},
      \"length\": \"${LENGTH}\",
      \"auto_correct\": true,
      \"max_corrections\": 2
    }")
  
  WORDS=$(echo "$RESPONSE" | grep -o '"final":[0-9]*' | grep -o '[0-9]*')
  VALID=$(echo "$RESPONSE" | grep -o '"valid":[a-z]*' | grep -o '[a-z]*$')
  CORRECTIONS=$(echo "$RESPONSE" | grep -o '"made":[0-9]*' | grep -o '[0-9]*' | head -1)
  
  if [ "$VALID" == "true" ]; then
    echo -e "  ${GREEN}✓${NC} Words: ${WORDS}, Corrections: ${CORRECTIONS}, Valid: ${GREEN}${VALID}${NC}"
  else
    echo -e "  ${YELLOW}!${NC} Words: ${WORDS}, Corrections: ${CORRECTIONS}, Valid: ${YELLOW}${VALID}${NC}"
  fi
done

echo ""

# Cleanup
echo -e "${YELLOW}Cleaning up test post...${NC}"
docker exec postimagegenerator-wordpress-1 wp post delete $POST_ID --force 2>/dev/null
echo -e "${GREEN}✓ Test complete!${NC}\n"

echo -e "${BLUE}=== Summary ===${NC}"
echo -e "The length correction feature:"
echo -e "  1. Generates initial content with GPT-4o"
echo -e "  2. Validates word count against target range (±10% tolerance)"
echo -e "  3. Automatically corrects if needed (expand or shorten)"
echo -e "  4. Returns detailed statistics including correction history"
echo -e "\nFor more details, check the logs in WordPress uploads directory."
echo -e "Documentation: ${WP_URL}/wp-content/plugins/ai-featured-image/documentation/setup.md\n"

