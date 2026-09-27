"""Punto de entrada Passenger para HostGator."""

import os
import sys

sys.path.insert(0, os.path.dirname(__file__))

from app import app as application  # noqa: E402
