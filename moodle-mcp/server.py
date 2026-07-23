"""Compatibility shim: the server now lives in the moodle_mcp package.

Keeps `python server.py` and `import server` working for existing MCP client
configs and CI. New installs should use the `moodle-mcp-bridge` console
script (pip install moodle-mcp-bridge).
"""

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from moodle_mcp.server import main, mcp  # noqa: E402,F401

if __name__ == "__main__":
    main()
