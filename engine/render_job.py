#!/usr/bin/env python3
"""Thin CLI adapter to reuse the *unmodified* original make.py renderer."""
import json
import sys
from pathlib import Path
from make import render
from settings import from_mapping


def main():
    if len(sys.argv) != 2:
        raise ValueError('usage: render_job.py /private/job.json')
    path = Path(sys.argv[1]).resolve()
    data = json.loads(path.read_text(encoding='utf-8'))
    options = from_mapping(data['settings'])
    options.title = data.get('title', '')
    render(options=options, progress=lambda pct, msg: print(json.dumps({'progress': pct, 'message': msg}), flush=True))


if __name__ == '__main__':
    try:
        main()
    except Exception as exc:
        print(json.dumps({'error': str(exc)}, ensure_ascii=False), file=sys.stderr)
        sys.exit(1)
