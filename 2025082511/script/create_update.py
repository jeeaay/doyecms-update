#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
DoyeCMS 完整更新包创建器
基于git暂存区自动创建更新包

功能：
1. 检测git暂存区文件
2. 复制文件到更新目录
3. 生成file_list.txt
4. 推送到远程仓库

使用方法：
1. 先使用git add 暂存需要更新的文件 注意一定要在暂存区 已经commit的内容会被跳过
2. 在DoyeCMS根目录下运行：uv run script/create_update.py 版本号
3. 或者：cd script && uv run create_update.py 版本号
"""

import os
import sys
import shutil
import subprocess
from datetime import datetime, timezone, timedelta
from pathlib import Path
import argparse

class UpdateCreator:
    def __init__(self, project_root=None):
        """
        初始化更新创建器
        
        Args:
            project_root: 项目根目录路径，默认为脚本所在目录的上级目录
        """
        if project_root:
            self.project_root = Path(project_root)
        else:
            # 脚本在script目录下，项目根目录是上级目录
            self.project_root = Path(__file__).parent.parent
        
        self.update_dir = self.project_root / 'update'
        
        # 确保更新目录存在
        self.update_dir.mkdir(exist_ok=True)
        
        print(f"项目根目录: {self.project_root}")
        print(f"更新目录: {self.update_dir}")
    
    def get_beijing_time(self):
        """
        获取北京时间的版本号格式 (YYYYMMDDHH)
        """
        # 北京时间 UTC+8
        beijing_tz = timezone(timedelta(hours=8))
        now = datetime.now(beijing_tz)
        return now.strftime('%Y%m%d%H')
    
    def get_staged_files(self):
        """
        获取git暂存区的文件列表
        
        Returns:
            list: 暂存区文件路径列表
        """
        try:
            # 切换到项目根目录
            os.chdir(self.project_root)
            
            # 获取暂存区文件
            result = subprocess.run(
                ['git', 'diff', '--cached', '--name-only'],
                capture_output=True,
                text=True,
                check=True
            )
            
            staged_files = [f.strip() for f in result.stdout.split('\n') if f.strip()]
            
            # 过滤掉 update_apply.txt 文件
            staged_files = [f for f in staged_files if f != 'update_apply.txt']
            
            if not staged_files:
                print("警告：暂存区没有文件")
                return []
            
            print(f"检测到 {len(staged_files)} 个暂存区文件:")
            for file in staged_files:
                print(f"  - {file}")
            
            return staged_files
            
        except subprocess.CalledProcessError as e:
            print(f"错误：获取git暂存区文件失败 - {e}")
            return []
        except FileNotFoundError:
            print("错误：未找到git命令，请确保git已安装")
            return []
    
    def copy_files_to_update(self, files, version):
        """
        将文件复制到更新目录
        
        Args:
            files: 文件路径列表
            version: 版本号
        
        Returns:
            bool: 是否成功
        """
        version_dir = self.update_dir / version
        
        try:
            # 创建版本目录
            version_dir.mkdir(parents=True, exist_ok=True)
            
            copied_files = []
            
            for file_path in files:
                source_file = self.project_root / file_path
                target_file = version_dir / file_path
                
                # 检查源文件是否存在
                if not source_file.exists():
                    print(f"警告：源文件不存在，跳过 - {file_path}")
                    continue
                
                # 创建目标目录
                target_file.parent.mkdir(parents=True, exist_ok=True)
                
                # 复制文件
                shutil.copy2(source_file, target_file)
                copied_files.append(file_path)
                print(f"已复制: {file_path}")
            
            if copied_files:
                print(f"成功复制 {len(copied_files)} 个文件到 {version_dir}")
                return True
            else:
                print("没有文件被复制")
                return False
                
        except Exception as e:
            print(f"错误：复制文件失败 - {e}")
            return False
    
    def scan_directory(self, directory):
        """
        递归扫描目录，返回所有文件的相对路径列表
        
        Args:
            directory: 要扫描的目录路径
        
        Returns:
            list: 文件相对路径列表
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
    
    def generate_file_list(self, version):
        """
        为指定版本目录生成file_list.txt文件
        
        Args:
            version: 版本号
        
        Returns:
            bool: 是否成功
        """
        version_dir = self.update_dir / version
        
        if not version_dir.exists():
            print(f"错误：版本目录 {version_dir} 不存在")
            return False
        
        print(f"正在生成文件列表：{version}")
        
        # 扫描文件
        files = self.scan_directory(version_dir)
        
        if not files:
            print(f"警告：版本目录 {version_dir} 中没有找到任何文件")
            return False
        
        # 生成file_list.txt
        file_list_path = version_dir / 'file_list.txt'
        
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
    
    def update_version_list(self, version):
        """
        更新update_list.txt文件
        
        Args:
            version: 新版本号
        
        Returns:
            bool: 是否成功
        """
        update_list_file = self.update_dir / 'update_list.txt'
        
        try:
            # 读取现有版本列表
            versions = set()
            if update_list_file.exists():
                with open(update_list_file, 'r', encoding='utf-8') as f:
                    versions = set(line.strip() for line in f if line.strip())
            
            # 添加新版本
            versions.add(version)
            
            # 排序版本列表
            sorted_versions = sorted(versions)
            
            # 写入文件
            with open(update_list_file, 'w', encoding='utf-8') as f:
                for v in sorted_versions:
                    f.write(v + '\n')
            
            print(f"已更新版本列表：{update_list_file}")
            print(f"当前版本列表：{sorted_versions}")
            
            return True
            
        except Exception as e:
            print(f"错误：更新版本列表失败 - {e}")
            return False
    
    def git_push_update(self):
        """
        推送更新到远程仓库
        
        Returns:
            bool: 是否成功
        """
        try:
            # 切换到update目录
            os.chdir(self.update_dir)
            
            # 检查是否是git仓库
            result = subprocess.run(
                ['git', 'status'],
                capture_output=True,
                text=True
            )
            
            if result.returncode != 0:
                print("警告：update目录不是git仓库，跳过推送")
                return False
            
            # 添加所有文件
            subprocess.run(['git', 'add', '.'], check=True)
            
            # 检查是否有变更
            result = subprocess.run(
                ['git', 'diff', '--cached', '--quiet'],
                capture_output=True
            )
            
            if result.returncode == 0:
                print("没有变更需要提交")
                return True
            
            # 提交变更
            commit_message = f"Add update package {self.get_beijing_time()}"
            subprocess.run(['git', 'commit', '-m', commit_message], check=True)
            
            # 推送到远程
            subprocess.run(['git', 'push'], check=True)
            
            print("成功推送更新到远程仓库")
            return True
            
        except subprocess.CalledProcessError as e:
            print(f"错误：git操作失败 - {e}")
            return False
        except Exception as e:
            print(f"错误：推送失败 - {e}")
            return False
        finally:
            # 切换回项目根目录
            os.chdir(self.project_root)
    
    def create_update_package(self, version=None, skip_git_check=False):
        """
        创建完整的更新包
        
        Args:
            version: 指定版本号，默认使用当前北京时间
            skip_git_check: 是否跳过git暂存区检查
        
        Returns:
            bool: 是否成功
        """
        if not version:
            version = self.get_beijing_time()
        
        print(f"\n=== 开始创建更新包 {version} ===")
        
        # 1. 获取暂存区文件
        if not skip_git_check:
            staged_files = self.get_staged_files()
            if not staged_files:
                print("没有暂存区文件，无法创建更新包")
                return False
        else:
            print("跳过git暂存区检查")
            staged_files = []
        
        # 2. 复制文件到更新目录
        if staged_files:
            if not self.copy_files_to_update(staged_files, version):
                print("复制文件失败")
                return False
        elif skip_git_check:
            # 如果跳过git检查，检查版本目录是否已存在
            version_dir = self.update_dir / version
            if not version_dir.exists():
                print(f"版本目录 {version_dir} 不存在，且没有暂存区文件可复制")
                print("请先添加文件到git暂存区，或手动创建版本目录")
                return False
        
        # 3. 生成文件列表
        if not self.generate_file_list(version):
            print("生成文件列表失败")
            return False
        
        # 4. 更新版本列表
        if not self.update_version_list(version):
            print("更新版本列表失败")
            return False
        
        # 5. 推送到远程仓库
        if not self.git_push_update():
            print("推送到远程仓库失败")
            return False
        
        print(f"\n=== 更新包 {version} 创建完成 ===")
        return True
    
    def regenerate_existing_version(self, version):
        """
        为已存在的版本目录重新生成file_list并推送
        
        Args:
            version: 版本号
        
        Returns:
            bool: 是否成功
        """
        print(f"\n=== 重新生成版本 {version} 的文件列表 ===")
        
        version_dir = self.update_dir / version
        if not version_dir.exists():
            print(f"错误：版本目录 {version_dir} 不存在")
            return False
        
        # 1. 重新生成文件列表
        if not self.generate_file_list(version):
            print("重新生成文件列表失败")
            return False
        
        # 2. 更新版本列表
        if not self.update_version_list(version):
            print("更新版本列表失败")
            return False
        
        # 3. 推送到远程仓库
        if not self.git_push_update():
            print("推送到远程仓库失败")
            return False
        
        print(f"\n=== 版本 {version} 文件列表重新生成完成 ===")
        return True

def main():
    parser = argparse.ArgumentParser(description='DoyeCMS 完整更新包创建器')
    parser.add_argument('version', nargs='?', help='版本号（格式：YYYYMMDDHH），如果目录存在则重新生成file_list')
    parser.add_argument('--skip-git', action='store_true', help='跳过git暂存区检查')
    parser.add_argument('--project-root', help='指定项目根目录路径')
    
    args = parser.parse_args()
    
    # 创建更新器实例
    creator = UpdateCreator(args.project_root)
    
    # 检查是否使用简化参数（直接传入版本号）
    if args.version:
        version_dir = creator.update_dir / args.version
        if version_dir.exists():
            print(f"版本目录 {args.version} 已存在，重新生成file_list并推送")
            success = creator.regenerate_existing_version(args.version)
        else:
            print(f"版本目录 {args.version} 不存在，创建新的更新包")
            success = creator.create_update_package(
                version=args.version,
                skip_git_check=args.skip_git
            )
    else:
        # 创建更新包
        success = creator.create_update_package(
            version=args.version,
            skip_git_check=args.skip_git
        )
    
    if success:
        print("\n✅ 更新包创建成功！")
        sys.exit(0)
    else:
        print("\n❌ 更新包创建失败！")
        sys.exit(1)

if __name__ == '__main__':
    main()