#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
DoyeCMS 更新文件列表生成器
用于在每次创建更新包后生成file_list.txt文件

使用方法：
1. 在DoyeCMS根目录下运行：python generate_file_list.py [version]
2. 如果不指定版本号，将扫描update目录下的所有版本
3. 生成的file_list.txt将保存在对应版本目录下
"""

import os
import sys
import argparse
from pathlib import Path

def scan_directory(directory):
    """
    递归扫描目录，返回所有文件的相对路径列表
    """
    files = []
    directory = Path(directory)
    
    if not directory.exists():
        print(f"错误：目录 {directory} 不存在")
        return files
    
    for item in directory.rglob('*'):
        if item.is_file():
            # 获取相对于版本目录的路径
            relative_path = item.relative_to(directory)
            # 统一使用正斜杠
            file_path = str(relative_path).replace('\\', '/')
            # 排除file_list.txt文件本身
            if file_path != 'file_list.txt':
                files.append(file_path)
    
    return sorted(files)

def generate_file_list(version_dir):
    """
    为指定版本目录生成file_list.txt文件
    """
    version_path = Path(version_dir)
    
    if not version_path.exists():
        print(f"错误：版本目录 {version_path} 不存在")
        return False
    
    print(f"正在扫描版本目录：{version_path}")
    
    # 扫描文件
    files = scan_directory(version_path)
    
    if not files:
        print(f"警告：版本目录 {version_path} 中没有找到任何文件")
        return False
    
    # 生成file_list.txt
    file_list_path = version_path / 'file_list.txt'
    
    try:
        with open(file_list_path, 'w', encoding='utf-8') as f:
            for file_path in files:
                f.write(file_path + '\n')
        
        print(f"成功生成文件列表：{file_list_path}")
        print(f"共包含 {len(files)} 个文件：")
        for file_path in files:
            print(f"  - {file_path}")
        
        return True
        
    except Exception as e:
        print(f"错误：生成文件列表失败 - {e}")
        return False

def main():
    parser = argparse.ArgumentParser(description='DoyeCMS 更新文件列表生成器')
    parser.add_argument('version', nargs='?', help='版本号（格式：YYYYMMDDHH）')
    parser.add_argument('--all', action='store_true', help='为所有版本生成文件列表')
    parser.add_argument('--update-dir', default='update', help='更新目录路径（默认：update）')
    
    args = parser.parse_args()
    
    # 获取脚本所在目录（DoyeCMS根目录）
    script_dir = Path(__file__).parent
    update_dir = script_dir / args.update_dir
    
    if not update_dir.exists():
        print(f"错误：更新目录 {update_dir} 不存在")
        sys.exit(1)
    
    success_count = 0
    total_count = 0
    
    if args.all:
        # 为所有版本生成文件列表
        print("正在为所有版本生成文件列表...")
        
        for item in update_dir.iterdir():
            if item.is_dir() and item.name.isdigit() and len(item.name) == 10:
                total_count += 1
                print(f"\n处理版本：{item.name}")
                if generate_file_list(item):
                    success_count += 1
    
    elif args.version:
        # 为指定版本生成文件列表
        version = args.version.strip()
        
        # 验证版本号格式
        if not version.isdigit() or len(version) != 10:
            print("错误：版本号格式不正确，应为10位数字（YYYYMMDDHH）")
            sys.exit(1)
        
        version_dir = update_dir / version
        total_count = 1
        
        if generate_file_list(version_dir):
            success_count = 1
    
    else:
        # 自动检测最新版本
        versions = []
        for item in update_dir.iterdir():
            if item.is_dir() and item.name.isdigit() and len(item.name) == 10:
                versions.append(item.name)
        
        if not versions:
            print("错误：在更新目录中没有找到任何版本目录")
            sys.exit(1)
        
        # 选择最新版本
        latest_version = max(versions)
        version_dir = update_dir / latest_version
        total_count = 1
        
        print(f"自动检测到最新版本：{latest_version}")
        if generate_file_list(version_dir):
            success_count = 1
    
    # 输出结果统计
    print(f"\n处理完成：成功 {success_count}/{total_count} 个版本")
    
    if success_count == total_count:
        print("所有版本的文件列表生成成功！")
        sys.exit(0)
    else:
        print("部分版本的文件列表生成失败，请检查错误信息")
        sys.exit(1)

if __name__ == '__main__':
    main()