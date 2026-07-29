Option Explicit

Dim shell, fileSystem, scriptDirectory, action, command
Set shell = CreateObject("WScript.Shell")
Set fileSystem = CreateObject("Scripting.FileSystemObject")

scriptDirectory = fileSystem.GetParentFolderName(WScript.ScriptFullName)
action = "status"

If WScript.Arguments.Count > 0 Then
    action = LCase(WScript.Arguments(0))
End If

command = "powershell.exe -NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File " _
    & Chr(34) & scriptDirectory & "\literacy-monitor-control.ps1" & Chr(34) _
    & " -Action " & action

shell.Run command, 0, False
