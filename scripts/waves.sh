#!/usr/bin/env bash
# Source this file: source scripts/waves.sh
# Then call: steps_for W2b
steps_for() {
  case "$1" in
    W0)   echo "0 6" ;;
    W1)   echo "A1.1 A1.2 A1.3 A1.4 A1.5 A1.6" ;;
    W1.5) echo "A3" ;;
    W2a)  echo "A2-a" ;;
    W2b)  echo "A2-b A2-c A2-d A2-e A2-f A2-g A2-h A2-i" ;;
    W3)   echo "1 5 9 10 12 13 14" ;;
    W4)   echo "2 3 11 7 8A" ;;
    W5)   echo "4 8B" ;;
    W6)   echo "15" ;;
    *)    echo "unknown wave: $1" >&2; return 1 ;;
  esac
}
